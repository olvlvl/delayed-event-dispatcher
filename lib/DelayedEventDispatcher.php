<?php

/*
 * This file is part of the olvlvl/delayed-event-dispatcher package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\DelayedEventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

class DelayedEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var callable
     */
    private $delayArbiter;

    /**
     * @var callable
     */
    private $exceptionHandler;

    /**
     * @var callable
     */
    private $flusher;

    /**
     * @var array
     */
    private $queue = [];

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param bool $disabled
     * @param callable|null $delayArbiter The delay arbiter determines whether an event should be delayed or not. It's
     *     a callable with the following signature: `function($event, string $eventName = null): bool`. The
     *     default delay arbiter just returns `true`, all events are delayed. Note: The delay arbiter is only invoked
     *     if delaying events is enabled.
     * @param callable|null $exceptionHandler This callable handles exceptions thrown during event dispatching. It's a
     *     callable with the following signature:
     *     `function(\Throwable $exception, $event, string $eventName = null): void`. The default exception
     *     handler just throws the exception.
     * @param callable|null $flusher By default, delayed events are dispatched with the decorated event dispatcher
     *     when flushed, but you can choose another solution entirely, like sending them to consumers using RabbitMQ or
     *     Kafka. The callable has the following signature: `function($event, string $eventName = null): void`.
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        bool $disabled = false,
        callable $delayArbiter = null,
        callable $exceptionHandler = null,
        callable $flusher = null
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->enabled = !$disabled;
        $this->delayArbiter = $delayArbiter ?: function () {
            return true;
        };
        $this->exceptionHandler = $exceptionHandler ?: function (Throwable $exception) {
            throw $exception;
        };
        $this->flusher = $flusher ?: function ($event, string $eventName = null) {
            $this->eventDispatcher->dispatch($event, $eventName);
        };
    }

    /**
     * @inheritdoc
     */
    public function dispatch($event, $eventName = null)
    {
        if ($this->shouldDelay($event, $eventName)) {
            $this->queue[] = [ $event, $eventName ];

            return $event;
        }

        return $this->eventDispatcher->dispatch($event, $eventName);
    }

    /**
     * @inheritdoc
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        $this->eventDispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * @inheritdoc
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->eventDispatcher->addSubscriber($subscriber);
    }

    /**
     * @inheritdoc
     */
    public function removeListener($eventName, $listener)
    {
        $this->eventDispatcher->removeListener($eventName, $listener);
    }

    /**
     * @inheritdoc
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->eventDispatcher->removeSubscriber($subscriber);
    }

    /**
     * @inheritdoc
     */
    public function getListeners($eventName = null)
    {
        return $this->eventDispatcher->getListeners($eventName);
    }

    /**
     * @inheritdoc
     */
    public function getListenerPriority($eventName, $listener)
    {
        return $this->eventDispatcher->getListenerPriority($eventName, $listener);
    }

    /**
     * @inheritdoc
     */
    public function hasListeners($eventName = null)
    {
        return $this->eventDispatcher->hasListeners($eventName);
    }

    /**
     * Dispatch all the events in the queue.
     *
     * Note: Exceptions raised during dispatching are caught and forwarded to the exception handler defined during
     * construct.
     */
    public function flush()
    {
        while (($queued = array_shift($this->queue))) {
            [ $event, $eventName ] = $queued;

            try {
                ($this->flusher)($event, $eventName);
            } catch (Throwable $e) {
                ($this->exceptionHandler)($e, $event, $eventName);
            }
        }
    }

    private function shouldDelay($event, $eventName = null): bool
    {
        return $this->enabled && ($this->delayArbiter)($event, $eventName);
    }
}
