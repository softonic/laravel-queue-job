<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Support\Arr;
use Override;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\QueueConfig;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQQueueWithDeclare extends RabbitMQQueue
{
    public function __construct(QueueConfig $config)
    {
        parent::__construct($config);

        $this->setConnection(new AMQPStreamConnection(
            Arr::get($this->getConfig()->getOptions(), 'host'),
            Arr::get($this->getConfig()->getOptions(), 'port'),
            Arr::get($this->getConfig()->getOptions(), 'user'),
            Arr::get($this->getConfig()->getOptions(), 'password'),
            Arr::get($this->getConfig()->getOptions(), 'vhost')
        ));

        $this->declareQueue($this->getQueue(), true, false, $this->getQueueArguments($this->getQueue()));

        foreach ($this->getQueueRoutingKeys() as $routingKey) {
            $this->bindQueue(
                $this->getQueue(),
                $this->getExchange(Arr::get($this->getConfig(), 'exchange')),
                $routingKey,
            );
        }
    }

    #[Override]
    protected function getQueueArguments(string $destination): array
    {
        $arguments = parent::getQueueArguments($destination);

        if ($this->getQueueMaxLength() !== 0) {
            $arguments['x-max-length'] = $this->getQueueMaxLength();
        }

        return $arguments;
    }

    private function getQueueMaxLength(): int
    {
        return (int) (Arr::get($this->getConfig()->getOptions(), 'max_length'));
    }

    private function getQueueRoutingKeys(): array
    {
        return Arr::get($this->getConfig()->getOptions(), 'routing_keys') ?: [];
    }
}
