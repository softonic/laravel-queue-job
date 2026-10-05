<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Support\Facades\Config;
use Override;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as BaseJob;

class RabbitMQJob extends BaseJob
{
    private const string HANDLER_NOT_CONFIGURED_MESSAGE = 'HandlerNotConfigured';

    private const array TRACE_CONTEXT_KEYS = ['traceparent' => true, 'tracestate' => true];

    private array $messageHandlers = [];

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    #[Override]
    public function payload()
    {
        $this->messageHandlers = $this->getMessageHandlers($this->getRabbitMQMessage()->getRoutingKey());
        $data                  = json_decode($this->getRawBody(), true);

        // The trace context goes at the root because that is where the OpenTelemetry
        // queue instrumentation looks: it extracts `traceparent` from `payload()` itself,
        // before `fire()` runs, so the consumer span continues the producer's trace.
        return [
            'job'  => $this->messageHandlers[0] ?? self::HANDLER_NOT_CONFIGURED_MESSAGE,
            'data' => $data,
        ] + array_intersect_key($this->producerContext($data), self::TRACE_CONTEXT_KEYS);
    }

    #[Override]
    public function fire(): void
    {
        $payload = $this->payload();

        // The worker generated its own X-Request-ID when it booted; the handlers belong
        // to the request that produced the event, so they run under that one instead,
        // and a queued handler carries it on. Restored afterwards so the next message
        // does not inherit it.
        $previousRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        $requestId         = $this->producerContext($payload['data'])['x-request-id'] ?? null;

        if (is_string($requestId) && $requestId !== '') {
            $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;
        }

        try {
            foreach ($this->messageHandlers as $messageHandler) {
                $messageHandler::dispatch($payload['data']);
            }
        } finally {
            if ($previousRequestId === null) {
                unset($_SERVER['HTTP_X_REQUEST_ID']);
            } else {
                $_SERVER['HTTP_X_REQUEST_ID'] = $previousRequestId;
            }
        }

        $this->instance = $this;
        $this->delete();
    }

    /**
     * The producer's context, as softonic/laravel-softonic-events puts it in every event
     * envelope under `meta.context`: `x-request-id`, and `traceparent` / `tracestate`
     * when the producing request was traced.
     *
     * @return array<string, mixed>
     */
    private function producerContext(mixed $data): array
    {
        $context = is_array($data) ? ($data['meta']['context'] ?? null) : null;

        return is_array($context) ? $context : [];
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
