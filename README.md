Laravel Queue Job
====================

[![Latest Version](https://img.shields.io/github/release/softonic/laravel-queue-job.svg?style=flat-square)](https://github.com/softonic/laravel-queue-job/releases)
[![Software License](https://img.shields.io/badge/license-Apache%202.0-blue.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/softonic/laravel-queue-job/master.svg?style=flat-square)](https://travis-ci.org/softonic/glaravel-queue-job)
[![Total Downloads](https://img.shields.io/packagist/dt/softonic/laravel-queue-job.svg?style=flat-square)](https://packagist.org/packages/softonic/laravel-queue-job)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/softonic/laravel-queue-job.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-queue-job "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/softonic/laravel-queue-job.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-queue-job "Percentage of issues still open")

Custom Job implementation for [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) library

Main features
-------------

* Add support to have multiple Handlers for the same Routing key.
* Assign your Routing keys with your Handlers in the queue config file.

Installation
-------------

You can require the last version of the package using composer
```bash
composer require softonic/laravel-queue-job
```

Usage
-------------

:warning: This library works on [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq).
If you have questions about how to configure connections, feel free to read the [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) documentation.

Replace your RabbitMQJob class in the queue config file.
```
'connections' => [
    // ...

    'rabbitmq' => [
        // ...

        'options' => [
            'queue' => [
                // ...

                'job' => \Softonic\LaravelQueueJob\RabbitMQJob::class,
            ],
        ],
    ],

    // ...    
],
```

Add your message_handlers mapping in queue config file:

```
'message_handlers' => [
        TestHandler::class => [ // Handler
            '#.test_v1.created.testevent', // Routing keys
            '#.test_v1.replaced.testevent',
            '#.test_v1.updated.testevent',
            'global.test_v1.updated.testevent',
            // ...
        ],
        AnotherTestHandler::class => [
            '#.test_v1.created.testevent',
            'global.test_v1.updated.testevent',
            // ...
        ],
        // ...
    ],
```

Testing with artisan
-------

Your 
``
php artisan queue:work {connection-name} --queue={queue-name}
``

Testing
-------

`softonic/laravel-queue-job` has a [PHPUnit](https://phpunit.de) test suite, and a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/).

To run the tests, run the following command from the project folder.

``` bash
$ make tests
```

To open a terminal in the dev environment:
``` bash
$ make debug
```

License
-------

The Apache 2.0 license. Please see [LICENSE](LICENSE) for more information.
