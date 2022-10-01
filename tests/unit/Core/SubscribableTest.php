<?php
/**
 *  * Created by mtils on 01.10.2022 at 10:02.
 **/

namespace Koansu\Tests\Core;

use Koansu\Testing\LoggingCallable;
use Koansu\Core\Contracts\Subscribable;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\SubscribableTrait;
use Koansu\Tests\TestCase;
use stdClass;

class SubscribableTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(
            Subscribable::class,
            $this->newSubscribable()
        );
    }

    /**
     * @test
     */
    public function getListeners_returns_listener()
    {
        $hookable = $this->newSubscribable();

        $listener = function () {};

        $hookable->on('get', $listener);

        $this->assertSame($listener, $hookable->getListeners('get')[0]);
    }

    /**
     * @test
     **/
    public function getListeners_with_unknown_position_throws_exception()
    {
        $hookable = $this->newSubscribable();

        $listener = function () {};

        $hookable->on('get', $listener);
        $this->expectException(ImplementationException::class);
        $this->assertSame($listener, $hookable->getListeners('get', 'before')[0]);
    }

    /**
     * @test
     */
    public function getListeners_return_empty_array_if_no_listeners_assigned()
    {
        $hookable = $this->newSubscribable();

        $this->assertEquals([], $hookable->getListeners('get'));
    }

    /**
     * @test
     */
    public function call_listeners()
    {
        $hookable = $this->newSubscribable();

        $listener = new LoggingCallable();

        $hookable->on('get', $listener);

        $hookable->fire('get', []);

        $hookable->fire('get', ['a']);

        $hookable->fire('get', ['a', 'b']);

        $hookable->fire('get', ['a', 'b', 'c']);

        $hookable->fire('get', ['a', 'b', 'c', 'd']);

        $hookable->fire('get', ['a', 'b', 'c', 'd', 'e']);

        $this->assertCount(6, $listener);

        $this->assertEquals([], $listener->args(0));
        $this->assertEquals(['a'], $listener->args(1));
        $this->assertEquals(['a', 'b'], $listener->args(2));
        $this->assertEquals(['a', 'b', 'c'], $listener->args(3));
        $this->assertEquals(['a', 'b', 'c', 'd'], $listener->args(4));
        $this->assertEquals(['a', 'b', 'c', 'd', 'e'], $listener->args(5));
    }

    /**
     * @test
     */
    public function listen_to_multiple_events()
    {
        $hookable = $this->newSubscribable();

        $listener = new LoggingCallable();

        $hookable->on(['get', 'set'], $listener);
        $hookable->fire('get', ['a']);
        $hookable->fire('set', ['b']);

        $this->assertCount(2, $listener);

        $this->assertEquals('b', $listener->arg(0));
        $this->assertEquals('a', $listener->arg(0, 0));

    }

    /**
     * @test
     */
    public function call_event_object()
    {
        $hookable = $this->newSubscribable();

        $listener = new LoggingCallable();

        $prototype = new stdClass();

        $hookable->on($prototype, $listener);

        $event = new stdClass();

        $hookable->fire(stdClass::class, [$event]);

        $this->assertSame($event, $listener->arg(0));
    }

    protected function newSubscribable() : SubscribableObject
    {
        return new SubscribableObject();
    }
}

class SubscribableObject implements Subscribable
{
    use SubscribableTrait;

    public function fire($event, array $args=[]) : bool
    {
        return $this->callOnListeners($event, $args);
    }
}