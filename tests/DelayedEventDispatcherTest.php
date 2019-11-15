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

use Exception;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * @group unit
 */
final class DelayedEventDispatcherTest extends TestCase
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

        parent::setUp();
    }

    /**
     * @test
     */
    public function shouldThrowException(): void
    {
        $event = new class {
        };
        $exception = new Exception;

        $this->eventDispatcher->dispatch($event)
            ->shouldBeCalled()->willThrow($exception);

        $dispatcher = $this->makeDelayedEventDispatcher();

        $dispatcher->dispatch($event);

        try {
            $dispatcher->flush();
        } catch (Throwable $e) {
            $this->assertSame($exception, $e);
            return;
        }

        $this->fail("Expected exception");
    }

    /**
     * @test
     */
    public function shouldInvokeExceptionHandler(): void
    {
        $event = new class {
        };
        $exception = new Exception;
        $invoked = false;

        $this->eventDispatcher->dispatch($event)->shouldBeCalled()->willThrow($exception);

        $dispatcher = $this->makeDelayedEventDispatcher(
            false,
            null,
            function (
                Throwable $actualException,
                $actualEvent
            ) use (
                &$invoked,
                $event,
                $exception
            ) {
                $invoked = true;
                $this->assertSame($exception, $actualException);
                $this->assertSame($event, $actualEvent);
            }
        );

        $dispatcher->dispatch($event);
        $dispatcher->flush();

        $this->assertTrue($invoked);
    }

    /**
     * @test
     */
    public function shouldInvokeFlusher(): void
    {
        $event = new class {
        };
        $invoked = false;

        $this->eventDispatcher->dispatch(Argument::any(), Argument::any())->shouldNotBeCalled();

        $dispatcher = $this->makeDelayedEventDispatcher(
            false,
            null,
            null,
            function ($actualEvent) use (&$invoked, $event) {
                $invoked = true;
                $this->assertSame($event, $actualEvent);
            }
        );

        $dispatcher->dispatch($event);
        $dispatcher->flush();

        $this->assertTrue($invoked);
    }

    /**
     * @test
     */
    public function shouldDispatchImmediatelyWhenDisabled(): void
    {
        $event = new class {
        };

        $this->eventDispatcher->dispatch($event)->shouldBeCalled()->willReturn($event);

        $dispatcher = $this->makeDelayedEventDispatcher(
            true
        );

        $this->assertSame($event, $dispatcher->dispatch($event));
    }

    /**
     * @test
     */
    public function shouldDelayDispatchWhenEnabled(): void
    {
        $event = new class {
        };

        $this->eventDispatcher->dispatch(Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->makeDelayedEventDispatcher()->dispatch($event);
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
        $event = new class {
        };

        if ($shouldDelay) {
            $this->eventDispatcher->dispatch(Argument::any(), Argument::any())->shouldNotBeCalled();
        } else {
            $this->eventDispatcher->dispatch($event)->shouldBeCalled()->willReturn($event);
        }

        $dispatcher = $this->makeDelayedEventDispatcher(
            $disabled,
            function ($actualEvent) use ($decision, $event) {
                $this->assertSame($event, $actualEvent);

                return $decision;
            }
        );

        $this->assertSame($event, $dispatcher->dispatch($event));
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

    private function makeDelayedEventDispatcher(
        $disabled = false,
        callable $delayArbiter = null,
        callable $exceptionHandler = null,
        callable $flusher = null
    ): DelayedEventDispatcher {
        return new DelayedEventDispatcher(
            $this->eventDispatcher->reveal(),
            $disabled,
            $delayArbiter,
            $exceptionHandler,
            $flusher
        );
    }
}
