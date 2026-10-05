<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Orchestra\Testbench\TestCase;
use Override;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJobTest extends TestCase
{
    private Container $containerMock;

    private RabbitMQQueue $rabbitmqMock;

    private AMQPMessage $amqpMessageMock;

    private const array MESSAGE_HANDLERS = [
        'message_handlers' => [
            TestHandlerOne::class => [
                '#.test_v1.handle_test_1',
                '#.test_v1.handle_test_2',
                '#.test_v1.handle_test_3',
            ],
            TestHandlerTwo::class => [
                '#.test_v1.handle_test_1',
                '#.test_v1.handle_test_2',
                '#.test_v1.handle_test_4',
            ],
        ],
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->containerMock   = Mockery::mock(Container::class);
        $this->rabbitmqMock    = Mockery::mock(RabbitMQQueue::class);
        $this->amqpMessageMock = Mockery::mock(AMQPMessage::class);

        Config::set('queue', self::MESSAGE_HANDLERS);
    }

    #[Test]
    public function whenGetPayloadItShouldReturnTheFirstJobFound(): void
    {
        $this->amqpMessageMock
            ->shouldReceive('getRoutingKey')
            ->andReturn('#.test_v1.handle_test_1');

        $this->amqpMessageMock
            ->shouldReceive('getBody')
            ->andReturn(json_encode([]));

        $rabbitMqJob = new RabbitMQJob(
            $this->containerMock,
            $this->rabbitmqMock,
            $this->amqpMessageMock,
            'test-connection',
            'test-queue'
        );

        $this->assertEquals(TestHandlerOne::class, $rabbitMqJob->payload()['job']);
    }

    #[Test]
    public function whenGetPayloadItShouldReturnTheCorrectJob(): void
    {
        $this->amqpMessageMock
            ->shouldReceive('getRoutingKey')
            ->andReturn('#.test_v1.handle_test_4');

        $this->amqpMessageMock
            ->shouldReceive('getBody')
            ->andReturn(json_encode([]));

        $rabbitMqJob = new RabbitMQJob(
            $this->containerMock,
            $this->rabbitmqMock,
            $this->amqpMessageMock,
            'test-connection',
            'test-queue'
        );

        $this->assertEquals(TestHandlerTwo::class, $rabbitMqJob->payload()['job']);
    }

    #[Test]
    public function whenGetPayloadItShouldReturnTheExpectedPayload(): void
    {
        $this->amqpMessageMock
            ->shouldReceive('getRoutingKey')
            ->andReturn('#.test_v1.handle_test_1');

        $this->amqpMessageMock
            ->shouldReceive('getBody')
            ->andReturn(json_encode(['id' => 'test_1', 'version' => 'v1']));

        $rabbitMqJob = new RabbitMQJob(
            $this->containerMock,
            $this->rabbitmqMock,
            $this->amqpMessageMock,
            'test-connection',
            'test-queue'
        );

        $this->assertEquals(
            [
                'job'  => TestHandlerOne::class,
                'data' => [
                    'id'      => 'test_1',
                    'version' => 'v1',
                ],
            ],
            $rabbitMqJob->payload()
        );
    }

    #[Test]
    public function whenFireItShouldDispatchAllHandlers(): void
    {
        $this->amqpMessageMock
            ->shouldReceive('getRoutingKey')
            ->andReturn('#.test_v1.handle_test_1');

        $this->amqpMessageMock
            ->shouldReceive('getBody')
            ->andReturn(json_encode(['id' => 'test_1', 'version' => 'v1']));

        $this->rabbitmqMock
            ->shouldReceive('ack')->once();

        $rabbitMqJob = new RabbitMQJob(
            $this->containerMock,
            $this->rabbitmqMock,
            $this->amqpMessageMock,
            'test-connection',
            'test-queue'
        );

        $rabbitMqJob->fire();

        Queue::assertPushed(TestHandlerOne::class);
        Queue::assertPushed(TestHandlerTwo::class);
    }

    #[Test]
    public function whenFireWithoutAssignedHandlerItShouldNotDispatchAnyHandler(): void
    {
        $this->amqpMessageMock
            ->shouldReceive('getRoutingKey')
            ->andReturn('#.non_existent_key');

        $this->amqpMessageMock
            ->shouldReceive('getBody')
            ->andReturn('');

        $this->rabbitmqMock
            ->shouldReceive('ack')->once();

        $rabbitMqJob = new RabbitMQJob(
            $this->containerMock,
            $this->rabbitmqMock,
            $this->amqpMessageMock,
            'test-connection',
            'test-queue'
        );

        $rabbitMqJob->fire();

        Queue::assertNothingPushed();
    }

    private const array TRACED_ENVELOPE = [
        'id'   => 'test_1',
        'meta' => [
            'context' => [
                'user'         => 'producer_v1',
                'ip'           => '127.0.0.1',
                'x-request-id' => 'producer-request-id',
                'traceparent'  => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
            ],
        ],
    ];

    #[Test]
    public function whenTheEventCarriesTraceContextPayloadExposesItAtTheRoot(): void
    {
        $rabbitMqJob = $this->jobFor('#.test_v1.handle_test_1', self::TRACED_ENVELOPE);

        $payload = $rabbitMqJob->payload();

        $this->assertSame(self::TRACED_ENVELOPE['meta']['context']['traceparent'], $payload['traceparent']);
        $this->assertArrayNotHasKey('x-request-id', $payload);
        $this->assertSame(self::TRACED_ENVELOPE, $payload['data']);
    }

    #[Test]
    public function whenFireItShouldRunHandlersUnderTheProducersRequestIdAndRestoreTheWorkersAfterwards(): void
    {
        Config::set('queue', ['message_handlers' => [RequestIdCapturingHandler::class => ['#.test_v1.handle_test_1']]]);
        $_SERVER['HTTP_X_REQUEST_ID'] = 'worker-request-id';

        $this->rabbitmqMock->shouldReceive('ack')->once();

        $this->jobFor('#.test_v1.handle_test_1', self::TRACED_ENVELOPE)->fire();

        Queue::assertPushed(
            RequestIdCapturingHandler::class,
            fn (RequestIdCapturingHandler $handler) => $handler->requestIdAtDispatch === 'producer-request-id',
        );
        $this->assertSame('worker-request-id', $_SERVER['HTTP_X_REQUEST_ID']);
    }

    #[Test]
    public function whenTheEventCarriesNoContextTheWorkersRequestIdIsKept(): void
    {
        Config::set('queue', ['message_handlers' => [RequestIdCapturingHandler::class => ['#.test_v1.handle_test_1']]]);
        $_SERVER['HTTP_X_REQUEST_ID'] = 'worker-request-id';

        $this->rabbitmqMock->shouldReceive('ack')->once();

        $this->jobFor('#.test_v1.handle_test_1', ['id' => 'test_1'])->fire();

        Queue::assertPushed(
            RequestIdCapturingHandler::class,
            fn (RequestIdCapturingHandler $handler) => $handler->requestIdAtDispatch === 'worker-request-id',
        );
    }

    private function jobFor(string $routingKey, array $body): RabbitMQJob
    {
        $this->amqpMessageMock->shouldReceive('getRoutingKey')->andReturn($routingKey);
        $this->amqpMessageMock->shouldReceive('getBody')->andReturn(json_encode($body));

        return new RabbitMQJob(
            $this->containerMock,
            $this->rabbitmqMock,
            $this->amqpMessageMock,
            'test-connection',
            'test-queue'
        );
    }
}
