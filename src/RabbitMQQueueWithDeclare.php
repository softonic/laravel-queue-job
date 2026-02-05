<?php

declare(strict_types=1);

namespace Softonic\LaravelQueueJob;

use Illuminate\Support\Arr;
use Override;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\QueueConfig;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQQueueWithDeclare extends RabbitMQQueue
{
    #[Override]
    public function __construct(QueueConfig $config)
    {
        parent::__construct($config);
    }

    #[Override]
    public function setConnection(\PhpAmqpLib\Connection\AbstractConnection $connection): RabbitMQQueue
    {
        parent::setConnection($connection);

        $this->declareQueue($this->getQueue(), true, false, $this->getQueueArguments($this->getQueue()));

        foreach ($this->getQueueRoutingKeys() as $routingKey) {
            $this->bindQueue(
                $this->getQueue(),
                $this->getExchange($this->getConfigExchange()),
                $routingKey,
            );
        }

        return $this;
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
        return (int) Arr::get($this->getRabbitMQConfig()->getOptions(), 'max_length', 0);
    }

    private function getQueueRoutingKeys(): array
    {
        return Arr::get($this->getRabbitMQConfig()->getOptions(), 'routing_keys', []);
    }

    private function getConfigExchange(): ?string
    {
        return Arr::get($this->getRabbitMQConfig()->getOptions(), 'exchange');
    }
}
