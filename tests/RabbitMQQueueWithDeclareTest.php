<?php

namespace Softonic\LaravelQueueJob;

use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Attributes\Test;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\QueueFactory;

class RabbitMQQueueWithDeclareTest extends TestCase
{
    private const string QUEUE = 'test-queue';

    private const string EXCHANGE = 'test-exchange';

    private AbstractConnection|MockInterface $connectionMock;

    private AMQPChannel|MockInterface $channelMock;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->channelMock    = Mockery::mock(AMQPChannel::class);
        $this->connectionMock = Mockery::mock(AbstractConnection::class);
        $this->connectionMock->allows('channel')->andReturn($this->channelMock);
    }

    #[Test]
    public function whenTheQueueFactoryBuildsItAsWorkerItShouldReturnAnInstanceOfTheClass(): void
    {
        $config = $this->config(['max_length' => 1000]);

        $queue = QueueFactory::make($config);

        $this->assertInstanceOf(RabbitMQQueueWithDeclare::class, $queue);
    }

    #[Test]
    public function whenTheConnectionIsSetItShouldDeclareTheQueueAndBindEveryRoutingKey(): void
    {
        $this->channelMock->expects('queue_declare')->once()->with(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            Mockery::on(static fn (AMQPTable $arguments): bool => $arguments->getNativeData() === ['x-max-length' => 1000])
        );
        $this->channelMock->expects('queue_bind')->once()->with(self::QUEUE, self::EXCHANGE, '#.test_v1.handle_test_1');
        $this->channelMock->expects('queue_bind')->once()->with(self::QUEUE, self::EXCHANGE, '#.test_v1.handle_test_2');
        $queue = QueueFactory::make($this->config([
            'max_length'   => '1000',
            'routing_keys' => ['#.test_v1.handle_test_1', '#.test_v1.handle_test_2'],
        ]));

        $result = $queue->setConnection($this->connectionMock);

        $this->assertSame($queue, $result);
        $this->assertSame($this->connectionMock, $queue->getConnection());
    }

    #[Test]
    public function whenNoMaxLengthIsConfiguredItShouldDeclareTheQueueWithoutArguments(): void
    {
        $this->channelMock->expects('queue_declare')->once()->with(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            Mockery::on(static fn (AMQPTable $arguments): bool => $arguments->getNativeData() === [])
        );
        $this->channelMock->allows('queue_bind');
        $queueWithNullMaxLength = QueueFactory::make($this->config(['max_length' => null]));

        $queueWithNullMaxLength->setConnection($this->connectionMock);

        $this->channelMock->expects('queue_declare')->once()->with(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            Mockery::on(static fn (AMQPTable $arguments): bool => $arguments->getNativeData() === [])
        );
        $queueWithoutMaxLength = QueueFactory::make($this->config());

        $queueWithoutMaxLength->setConnection($this->connectionMock);
    }

    #[Test]
    public function whenMaxLengthIsZeroItShouldNotAddTheMaxLengthArgument(): void
    {
        $this->channelMock->expects('queue_declare')->once()->with(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            Mockery::on(static fn (AMQPTable $arguments): bool => !array_key_exists('x-max-length', $arguments->getNativeData()))
        );
        $this->channelMock->allows('queue_bind');
        $queue = QueueFactory::make($this->config(['max_length' => 0]));

        $queue->setConnection($this->connectionMock);
    }

    #[Test]
    public function whenMaxLengthIsNegativeItShouldNotAddTheMaxLengthArgument(): void
    {
        $this->channelMock->expects('queue_declare')->once()->with(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            Mockery::on(static fn (AMQPTable $arguments): bool => !array_key_exists('x-max-length', $arguments->getNativeData()))
        );
        $this->channelMock->allows('queue_bind');
        $queue = QueueFactory::make($this->config(['max_length' => -5]));

        $queue->setConnection($this->connectionMock);
    }

    #[Test]
    public function whenThereAreNoRoutingKeysItShouldDeclareTheQueueWithoutBindingIt(): void
    {
        $this->channelMock->expects('queue_declare')->once()->with(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            Mockery::type(AMQPTable::class)
        );
        $this->channelMock->expects('queue_bind')->never();
        $queue = QueueFactory::make($this->config(['routing_keys' => []]));

        $queue->setConnection($this->connectionMock);
    }

    #[Test]
    public function whenRoutingKeysIsASingleStringItShouldBindThatRoutingKey(): void
    {
        $this->channelMock->expects('queue_declare')->once();
        $this->channelMock->expects('queue_bind')->once()->with(self::QUEUE, self::EXCHANGE, '#.test_v1.handle_test_1');
        $queue = QueueFactory::make($this->config(['routing_keys' => '#.test_v1.handle_test_1']));

        $queue->setConnection($this->connectionMock);
    }

    private function config(array $queueOptions = []): array
    {
        return [
            'driver'  => 'rabbitmq',
            'queue'   => self::QUEUE,
            'worker'  => RabbitMQQueueWithDeclare::class,
            'options' => [
                'queue' => [
                    'exchange'      => self::EXCHANGE,
                    'exchange_type' => 'topic',
                    'routing_keys'  => ['#.test_v1.handle_test_1'],
                    ...$queueOptions,
                ],
            ],
        ];
    }
}
