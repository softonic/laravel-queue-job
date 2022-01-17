<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Support\Arr;
use PhpAmqpLib\Connection\AbstractConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQQueueWithDeclare extends RabbitMQQueue
{
    public function __construct(AbstractConnection $connection, string $default, array $options = [])
    {
        parent::__construct($connection, $default, $options);

        $this->declareQueue($this->getQueue(), true, false, $this->getQueueArguments($this->getQueue()));

        foreach ($this->getQueueRoutingKeys() as $routingKey) {
            $this->bindQueue(
                $this->getQueue(),
                $this->getExchange(Arr::get($options, 'exchange')),
                $routingKey,
            );
        }
    }

    public function getQueueArguments(string $destination): array
    {
        $arguments = parent::getQueueArguments($destination);

        if ($this->getQueueMaxLength()) {
            $arguments['x-max-length'] = $this->getQueueMaxLength();
        }

        return $arguments;
    }

    private function getQueueMaxLength(): int
    {
        return (int) (Arr::get($this->options, 'max_length'));
    }

    private function getQueueRoutingKeys(): array
    {
        return Arr::get($this->options, 'routing_keys') ?: [];
    }
}
