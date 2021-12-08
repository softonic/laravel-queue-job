<?php

namespace Softonic\LaravelQueueJob;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TestHandlerOne implements ShouldQueue
{
    use Dispatchable;
}
