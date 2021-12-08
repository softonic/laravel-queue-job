<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TestHandlerTwo implements ShouldQueue
{
    use Dispatchable;
}
