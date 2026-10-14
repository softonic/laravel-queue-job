<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Support\Arr;
use Override;
use PhpAmqpLib\Connection\AbstractConnection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQQueueWithDeclare extends RabbitMQQueue
{
    #[Override]
    public function setConnection(AbstractConnection $connection): RabbitMQQueue
    {
        parent::setConnection($connection);

        $this->declareQueue($this->getQueue(), true, false, $this->getQueueArguments($this->getQueue()));

        foreach ($this->getQueueRoutingKeys() as $routingKey) {
            $this->bindQueue($this->getQueue(), $this->getExchange(), $routingKey);
        }

        return $this;
    }

    #[Override]
    protected function getQueueArguments(string $destination): array
    {
        $arguments = parent::getQueueArguments($destination);

        if ($this->getQueueMaxLength() > 0) {
            $arguments['x-max-length'] = $this->getQueueMaxLength();
        }

        return $arguments;
    }

    private function getQueueMaxLength(): int
    {
        return (int) (Arr::get($this->getRabbitMQConfig()->getOptions(), 'max_length'));
    }

    private function getQueueRoutingKeys(): array
    {
        return Arr::wrap(Arr::get($this->getRabbitMQConfig()->getOptions(), 'routing_keys') ?: []);
    }
}
