<?php

declare(strict_types=1);

namespace Softonic\LaravelQueueJob;

use Mockery;
use Orchestra\Testbench\TestCase;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\Attributes\Test;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\QueueConfig;

class RabbitMQQueueWithDeclareTest extends TestCase
{
    private AbstractConnection $connectionMock;

    private AMQPChannel $channelMock;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->channelMock = Mockery::mock(AMQPChannel::class);
        $this->connectionMock = Mockery::mock(AbstractConnection::class);

        $this->connectionMock
            ->shouldReceive('channel')
            ->andReturn($this->channelMock);
    }

    #[Test]
    public function whenConstructedItShouldAcceptQueueConfig(): void
    {
        $config = new QueueConfig();
        $config->setQueue('test-queue');

        $queue = new RabbitMQQueueWithDeclare($config);

        $this->assertInstanceOf(RabbitMQQueueWithDeclare::class, $queue);
        $this->assertEquals('test-queue', $queue->getQueue());
    }

    #[Test]
    public function whenSetConnectionItShouldDeclareQueueAndBindRoutingKeys(): void
    {
        $config = new QueueConfig();
        $config->setQueue('test-queue');
        $config->setExchange('test-exchange');
        $config->setOptions([
            'routing_keys' => ['routing.key.one', 'routing.key.two'],
            'exchange' => 'custom-exchange',
        ]);

        $queue = new RabbitMQQueueWithDeclare($config);

        $this->channelMock
            ->shouldReceive('queue_declare')
            ->once()
            ->with('test-queue', false, true, false, false, false, Mockery::type('PhpAmqpLib\Wire\AMQPTable'));

        $this->channelMock
            ->shouldReceive('queue_bind')
            ->once()
            ->with('test-queue', 'custom-exchange', 'routing.key.one');

        $this->channelMock
            ->shouldReceive('queue_bind')
            ->once()
            ->with('test-queue', 'custom-exchange', 'routing.key.two');

        $result = $queue->setConnection($this->connectionMock);

        $this->assertSame($queue, $result);
    }

    #[Test]
    public function whenSetConnectionWithNoRoutingKeysItShouldOnlyDeclareQueue(): void
    {
        $config = new QueueConfig();
        $config->setQueue('test-queue');
        $config->setOptions([]);

        $queue = new RabbitMQQueueWithDeclare($config);

        $this->channelMock
            ->shouldReceive('queue_declare')
            ->once()
            ->with('test-queue', false, true, false, false, false, Mockery::type('PhpAmqpLib\Wire\AMQPTable'));

        $this->channelMock
            ->shouldReceive('queue_bind')
            ->never();

        $queue->setConnection($this->connectionMock);
    }

    #[Test]
    public function whenMaxLengthIsSetItShouldIncludeInQueueArguments(): void
    {
        $config = new QueueConfig();
        $config->setQueue('test-queue');
        $config->setOptions([
            'max_length' => 1000,
        ]);

        $queue = new RabbitMQQueueWithDeclare($config);

        $this->channelMock
            ->shouldReceive('queue_declare')
            ->once()
            ->withArgs(function ($queueName, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments) {
                $args = $arguments->getNativeData();

                return $queueName === 'test-queue'
                    && isset($args['x-max-length'])
                    && $args['x-max-length'] === 1000;
            });

        $queue->setConnection($this->connectionMock);
    }
}
