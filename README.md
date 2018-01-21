# Delayed event dispatcher

[![Release](https://img.shields.io/packagist/v/olvlvl/delayed-event-dispatcher.svg)](https://packagist.org/packages/olvlvl/delayed-event-dispatcher)
[![Build Status](https://img.shields.io/travis/olvlvl/delayed-event-dispatcher.svg)](http://travis-ci.org/olvlvl/delayed-event-dispatcher)
[![Code Quality](https://img.shields.io/scrutinizer/g/olvlvl/delayed-event-dispatcher.svg)](https://scrutinizer-ci.com/g/olvlvl/delayed-event-dispatcher)
[![Code Coverage](https://img.shields.io/coveralls/olvlvl/delayed-event-dispatcher.svg)](https://coveralls.io/r/olvlvl/delayed-event-dispatcher)
[![Packagist](https://img.shields.io/packagist/dt/olvlvl/delayed-event-dispatcher.svg)](https://packagist.org/packages/olvlvl/delayed-event-dispatcher)

The `olvlvl/delayed-event-dispatcher` package provides an event dispatcher that delays event dispatching to a later time
in the application life.

A delayed event dispatcher is useful to reduce the response time of your HTTP application when events can perfectly be
dispatched after the response has been sent. For instance, updating an entity that would require clearing cache,
performing projections, or reindexing, all of which have nothing to do with the response itself.

Because you're probably using one event dispatcher for your application you don't want all events to be delayed, most of
them have to be dispatched immediately for your application to run properly, that's why you can specify an arbiter to
determine which events to delay and which not to.

Finally, because you want all delayed events to be dispatched when you flush them—even when an exception is thrown—you
can provide an exception handler. It's up to you to decide what to do with them. You'll probably want to recover, log
the exception, and continue with dispatching the other events.

**Disclaimer:** The delayed event dispatcher is a decorator that is meant to be used together with
[symfony/event-dispatcher][]. 





## Instantiating a delayed event dispatcher

The delayed event dispatcher is a decorator, which means you'll need another event dispatcher to decorate. Methods are
forwarded to the decorated instance, except of course for the `dispatch()` method.

```php
<?php

use olvlvl\DelayedEventDispatcher\DelayedEventDispatcher;

/* @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */

$delayedEventDispatcher = new DelayedEventDispatcher($eventDispatcher); 
$delayedEventDispatcher->addListener('my_event', $listener);
```





### Instantiating an inactive delayed event dispatcher

By default the delayed event dispatcher is _active_, which means it will delay events. This is fine for your HTTP
application, but that's not something you want for the console or the consumer application. You can create a _disabled_
delayed event dispatcher be defining the `disabled` option as `true`.

```php
<?php

use olvlvl\DelayedEventDispatcher\DelayedEventDispatcher;

/* @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */

$disabledDelayedEventDispatcher = new DelayedEventDispatcher($eventDispatcher, true);
```

This is especially useful when your HTTP/console/consumer applications are deployed using the same artifact, that you
can customize using environment variables.

```php
<?php

use olvlvl\DelayedEventDispatcher\DelayedEventDispatcher;

/* @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */

$disabledDelayedEventDispatcher = new DelayedEventDispatcher(
    $eventDispatcher, 
    filter_var(getenv('MYAPP_DISABLE_DELAYED_EVENT_DISPATCHER'), FILTER_VALIDATE_BOOLEAN)
);
```





## Dispatching delayed events

Delayed events are dispatched with the `flush()` method.

```php
<?php

use olvlvl\DelayedEventDispatcher\DelayedEventDispatcher;

/* @var DelayedEventDispatcher $delayedEventDispatcher */

$delayedEventDispatcher->dispatch('my_event');
$delayedEventDispatcher->dispatch('my_other_event');
$delayedEventDispatcher->flush();
```





## Deciding which events to delay and which not to

By default all events are delayed—if the delayed event dispatcher was created with `disabled = false`—but you can supply
an arbiter to choose which events to delay and which not to. You can use the `Delayable` interface to mark your events,
but it's not a requirement. The arbiter is a simple callable, its implementation is up to you.

```php
<?php

use olvlvl\DelayedEventDispatcher\Delayable;
use olvlvl\DelayedEventDispatcher\DelayedEventDispatcher;
use Symfony\Component\EventDispatcher\Event;

$arbiter = function (string $eventName, Event $event) {
    return $event instanceof Delayable;
};

/* @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */

$disabledDelayedEventDispatcher = new DelayedEventDispatcher($eventDispatcher, false, $arbiter);
```





## Handling exceptions

By default exceptions thrown during the dispatching of events are not recovered, the dispatching halts, leaving delayed
events in the queue. If you want to recover from these exceptions, and make sure all the events are dispatched, you'll
want to provide and exception handler.

```php
<?php

use olvlvl\DelayedEventDispatcher\DelayedEventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/* @var \Psr\Log\LoggerInterface $logger */

$exceptionHandler = function (\Throwable $error, string $eventName, Event $event = null) use ($logger) {
    // The exception is recovered, we log it to fix it later
    $logger->danger($error);
};

/* @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */

$disabledDelayedEventDispatcher = new DelayedEventDispatcher($eventDispatcher, false, null, $exceptionHandler);
```





----------





## Requirements

The package requires PHP 7.1 or later.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/):

	$ composer require olvlvl/delayed-event-dispatcher





### Cloning the repository

The package is [available on GitHub](https://github.com/olvlvl/delayed-event-dispatcher),
its repository can be cloned with the following command line:

	$ git clone https://github.com/olvlvl/delayed-event-dispatcher.git





## Documentation

You can generate the documentation for the package and its dependencies with the `make doc` command.
The documentation is generated in the `build/docs` directory. [ApiGen](http://apigen.org/) is
required. The directory can later be cleaned with the `make clean` command.





## Testing

The test suite is ran with the `make test` command. [PHPUnit](https://phpunit.de/) and
[Composer](http://getcomposer.org/) need to be globally available to run the suite. The command
installs dependencies as required. The `make test-coverage` command runs test suite and also creates
an HTML coverage report in `build/coverage`. The directory can later be cleaned with the
`make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://img.shields.io/travis/olvlvl/delayed-event-dispatcher.svg)](http://travis-ci.org/olvlvl/delayed-event-dispatcher)
[![Code Coverage](https://img.shields.io/coveralls/olvlvl/delayed-event-dispatcher.svg)](https://coveralls.io/r/olvlvl/delayed-event-dispatcher)





## License

**olvlvl/delayed-event-dispatcher** is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.






[symfony/event-dispatcher]: https://github.com/symfony/event-dispatcher
