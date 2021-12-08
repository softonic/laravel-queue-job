<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQJobTest extends TestCase
{
    private Container $containerMock;
    private RabbitMQQueue $rabbitmqMock;
    private AMQPMessage $amqpMessageMock;

    private const MESSAGE_HANDLERS = [
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

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->containerMock   = \Mockery::mock(Container::class);
        $this->rabbitmqMock    = \Mockery::mock(RabbitMQQueue::class);
        $this->amqpMessageMock = \Mockery::mock(AMQPMessage::class);

        Config::set('queue', self::MESSAGE_HANDLERS);
    }

    /** @test */
    public function whenGetPayloadItShouldReturnTheFirstJobFound()
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

    /** @test */
    public function whenGetPayloadItShouldReturnTheCorrectJob()
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

    /** @test */
    public function whenGetPayloadItShouldReturnTheExpectedPayload()
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

    /** @test */
    public function whenFireItShouldDispatchAllHandlers()
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
}
