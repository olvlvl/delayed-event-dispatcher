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

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DelayedEventDispatcherTest extends TestCase
{
    /**
     * @test
     */
    public function shouldThrowException()
    {
        $eventName = uniqid();
        $event = new class extends Event {
        };
        $exception = new \Exception;

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $event, $exception) {
                $dispatcher->dispatch($eventName, $event)->shouldBeCalled()->willThrow($exception);
            }
        );

        $dispatcher->dispatch($eventName, $event);

        try {
            $dispatcher->flush();
        } catch (\Throwable $e) {
            $this->assertSame($exception, $e);
            return;
        }

        $this->fail("Expected exception");
    }

    /**
     * @test
     */
    public function shouldInvokeExceptionHandler()
    {
        $eventName = uniqid();
        $event = new class extends Event {
        };
        $exception = new \Exception;
        $invoked = false;

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $event, $exception) {
                $dispatcher->dispatch($eventName, $event)->shouldBeCalled()->willThrow($exception);
            },
            false,
            null,
            function (
                \Throwable $actualException,
                $actualEventName,
                $actualEvent = null
            ) use (
                &$invoked,
                $eventName,
                $event,
                $exception
            ) {
                $invoked = true;
                $this->assertSame($exception, $actualException);
                $this->assertSame($eventName, $actualEventName);
                $this->assertSame($event, $actualEvent);
            }
        );

        $dispatcher->dispatch($eventName, $event);
        $dispatcher->flush();

        $this->assertTrue($invoked);
    }

    /**
     * @test
     */
    public function shouldDispatchImmediatelyWhenDisabled()
    {
        $eventName = uniqid();
        $event = new class extends Event {
        };

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $event) {
                $dispatcher->dispatch($eventName, $event)->shouldBeCalled();
            },
            true
        );

        $dispatcher->dispatch($eventName, $event);
    }

    /**
     * @test
     */
    public function shouldDelayDispatchWhenEnabled()
    {
        $eventName = uniqid();
        $event = new class extends Event {
        };

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) {
                $dispatcher->dispatch(Argument::any(), Argument::any())->shouldNotBeCalled();
            }
        );

        $dispatcher->dispatch($eventName, $event);
    }

    /**
     * @test
     * @dataProvider provideDispatchAccordingToStateAndArbiter
     *
     * @param bool $disabled
     * @param bool $decision
     * @param bool $shouldDelay
     */
    public function shouldDispatchAccordingToStateAndArbiter(bool $disabled, bool $decision, bool $shouldDelay)
    {
        $eventName = uniqid();
        $event = new class extends Event {
        };

        $dispatcher = $this->makeDelayedEventDispatcher(
            $shouldDelay
                ? function ($dispatcher) {
                    $dispatcher->dispatch(Argument::any(), Argument::any())->shouldNotBeCalled();
                }
                : function ($dispatcher) use ($eventName, $event) {
                    $dispatcher->dispatch($eventName, $event)->shouldBeCalled()->willReturn($event);
                },
            $disabled,
            function ($actualEventName, $actualEvent) use ($decision, $eventName, $event) {
                $this->assertSame($eventName, $actualEventName);
                $this->assertSame($event, $actualEvent);

                return $decision;
            }
        );

        $this->assertSame($event, $dispatcher->dispatch($eventName, $event));
    }

    public function provideDispatchAccordingToStateAndArbiter(): array
    {
        return [

            [ true, true, false ],
            [ true, false, false ],
            [ false, false, false ],
            [ false, true, true ],

        ];
    }

    public function testAddListener()
    {
        $eventName = uniqid();
        $listener = function () {
        };
        $priority = mt_rand(10, 20);

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $listener, $priority) {
                $dispatcher->addListener($eventName, $listener, $priority)
                    ->shouldBeCalled();
            }
        );

        $dispatcher->addListener($eventName, $listener, $priority);
    }

    public function testRemoveListener()
    {
        $eventName = uniqid();
        $listener = function () {
        };

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $listener) {
                $dispatcher->removeListener($eventName, $listener)
                    ->shouldBeCalled();
            }
        );

        $dispatcher->removeListener($eventName, $listener);
    }

    public function testGetListeners()
    {
        $eventName = uniqid();
        $listeners = [ function () {
        } ];

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $listeners) {
                $dispatcher->getListeners($eventName)
                    ->shouldBeCalled()->willReturn($listeners);
            }
        );

        $this->assertSame($listeners, $dispatcher->getListeners($eventName));
    }

    public function testHasListeners()
    {
        $eventName = uniqid();

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName) {
                $dispatcher->hasListeners($eventName)
                    ->shouldBeCalled()->willReturn(true);
            }
        );

        $this->assertTrue($dispatcher->hasListeners($eventName));
    }

    public function testGetListenerPriority()
    {
        $eventName = uniqid();
        $listener = function () {
        };
        $priority = mt_rand(10, 20);

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($eventName, $listener, $priority) {
                $dispatcher->getListenerPriority($eventName, $listener)
                    ->shouldBeCalled()->willReturn($priority);
            }
        );

        $this->assertSame($priority, $dispatcher->getListenerPriority($eventName, $listener));
    }

    public function testAddSubscriber()
    {
        $subscriber = $this->prophesize(EventSubscriberInterface::class)->reveal();

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($subscriber) {
                $dispatcher->addSubscriber($subscriber)
                    ->shouldBeCalled();
            }
        );

        $dispatcher->addSubscriber($subscriber);
    }

    public function testRemoveSubscriber()
    {
        $subscriber = $this->prophesize(EventSubscriberInterface::class)->reveal();

        $dispatcher = $this->makeDelayedEventDispatcher(
            function ($dispatcher) use ($subscriber) {
                $dispatcher->removeSubscriber($subscriber)
                    ->shouldBeCalled();
            }
        );

        $dispatcher->removeSubscriber($subscriber);
    }

    private function makeDelayedEventDispatcher(
        callable $initEventDispatcher = null,
        $disabled = false,
        callable $delayArbiter = null,
        callable $exceptionHandler = null
    ): DelayedEventDispatcher {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

        if ($initEventDispatcher) {
            $initEventDispatcher($eventDispatcher);
        }

        return new DelayedEventDispatcher($eventDispatcher->reveal(), $disabled, $delayArbiter, $exceptionHandler);
    }
}
