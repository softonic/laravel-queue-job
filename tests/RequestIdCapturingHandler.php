<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Records the X-Request-ID in effect when it was dispatched, which is what a handler
 * using laravel-base's SerializesModels would carry into the queue.
 */
class RequestIdCapturingHandler implements ShouldQueue
{
    use Dispatchable;

    public readonly ?string $requestIdAtDispatch;

    public function __construct(public readonly array $data)
    {
        $this->requestIdAtDispatch = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
    }
}
