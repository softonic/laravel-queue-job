Laravel Queue Job
====================

[![Latest Version](https://img.shields.io/github/release/softonic/laravel-queue-job.svg?style=flat-square)](https://github.com/softonic/laravel-queue-job/releases)
[![Software License](https://img.shields.io/badge/license-Apache%202.0-blue.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/github/actions/workflow/status/softonic/laravel-queue-job/tests.yml?branch=master&style=flat-square)](https://github.com/softonic/laravel-queue-job/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/softonic/laravel-queue-job.svg?style=flat-square)](https://packagist.org/packages/softonic/laravel-queue-job)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/softonic/laravel-queue-job.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-queue-job "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/softonic/laravel-queue-job.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-queue-job "Percentage of issues still open")

Custom Job implementation for [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) library

:warning: This library works on [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq).
If you have questions about how to configure connections, feel free to read the [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) documentation.

Requirements
-------------

- PHP >= 8.5
- Laravel 12.x

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

Declaring the queue on startup
-------------

If you want the queue to be declared and bound to your routing keys automatically when the connection is opened
(useful in local/dev environments), use `RabbitMQQueueWithDeclare` as the worker of your connection:

```
'connections' => [
    // ...

    'rabbitmq' => [
        // ...

        'worker' => \Softonic\LaravelQueueJob\RabbitMQQueueWithDeclare::class,

        'options' => [
            'queue' => [
                // ...

                'exchange'     => 'my-exchange',
                'routing_keys' => [ // An array, or a string for a single routing key
                    '#.test_v1.created.testevent',
                    'global.test_v1.updated.testevent',
                ],
                'max_length'   => 1000, // Optional. Adds x-max-length when greater than 0
            ],
        ],
    ],

    // ...
],
```

The queue is declared as durable and bound to the configured exchange once per resolved connection.

:warning: This happens in every process that resolves the connection, not only in `queue:work`.
If the queue already exists with different arguments (e.g. another `max_length`), RabbitMQ rejects the declaration
with a `PRECONDITION_FAILED` error.

Testing with artisan
-------

Your 
``
php artisan queue:work {connection-name} --queue={queue-name}
``

Testing
-------

`softonic/laravel-queue-job` has a [PHPUnit](https://phpunit.de) test suite, and a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).

To run the tests:

```bash
docker compose run --rm tests
```

To run PHPUnit only:

```bash
docker compose run --rm phpunit
```

To fix code style issues:

```bash
docker compose run --rm fixcs
```

To open a terminal in the dev environment:

```bash
docker compose run --rm php sh
```

License
-------

The Apache 2.0 license. Please see [LICENSE](LICENSE) for more information.
