<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Support\Facades\Config;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as BaseJob;

class RabbitMQJob extends BaseJob
{
    private array $messageHandlers = [];

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload()
    {
        $this->messageHandlers = $this->getMessageHandlers($this->getRabbitMQMessage()->getRoutingKey());

        if (empty($this->messageHandlers)) {
            return [];
        }

        return [
            'job'  => $this->messageHandlers[0],
            'data' => json_decode($this->getRawBody(), true),
        ];
    }

    public function fire()
    {
        $payload = $this->payload();

        foreach ($this->messageHandlers as $messageHandler) {
            $messageHandler::dispatch($payload['data']);
        }

        $this->instance = $this;
        $this->delete();
    }

    private function getMessageHandlers(string $routingKey): array
    {
        $messageHandlers = [];
        foreach (Config::get('queue')['message_handlers'] as $job => $messageHandlerRoutingKeys) {
            $messageHandlerRoutingKeysRegex = $this->getRoutingKeysRegex($messageHandlerRoutingKeys);

            if (preg_match($messageHandlerRoutingKeysRegex, $routingKey)) {
                $messageHandlers[] = $job;
            }
        }

        return $messageHandlers;
    }

    private function getRoutingKeysRegex(array $messageHandlerRoutingKeys): string
    {
        $messageHandlerRoutingKeysFlattened = implode('|', $messageHandlerRoutingKeys);
        $messageHandlerRoutingKeysRegex     = preg_replace(
            ['/\./', '/#/'],
            ['\\.', '.*'],
            $messageHandlerRoutingKeysFlattened
        );

        return '/' . $messageHandlerRoutingKeysRegex . '/';
    }
}
