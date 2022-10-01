<?php

namespace Koansu\Tests\Core;

use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\Core\Contracts\Hookable;
use Koansu\Core\Exceptions\HookNotFoundException;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\HookableTrait;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\TestCase;
use stdClass;

class HookableTraitTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(
            HasMethodHooks::class,
            $this->newHookable()
        );
    }

    /**
     * @test
     */
    public function getListeners_returns_before_listener()
    {
        $hookable = $this->newHookable(['get']);

        $listener = function () {};

        $hookable->onBefore('get', $listener);

        $this->assertSame($listener, $hookable->getListeners('get', 'before')[0]);
    }

    /**
     * @test
     */
    public function getListeners_returns_after_listener()
    {
        $hookable = $this->newHookable(['get']);

        $listener = function () {};

        $hookable->onAfter('get', $listener);

        $this->assertSame($listener, $hookable->getListeners('get', 'after')[0]);
    }

    /**
     * @test
     */
    public function getListeners_with_unknown_position_throws_exception()
    {
        $hookable = $this->newHookable(['get']);

        $listener = function () {};

        $hookable->onAfter('get', $listener);

        $this->expectException(ImplementationException::class);
        $hookable->getListeners('get', 'foo');
    }

    /**
     * @test
     */
    public function getListeners_return_empty_array_if_no_listeners_assigned()
    {
        $hookable = $this->newHookable(['get']);

        $this->assertEquals([], $hookable->getListeners('get', 'before'));
    }

    /**
     * @test
     */
    public function call_before_listeners()
    {
        $hookable = $this->newHookable(['get']);

        $listener = new LoggingCallable();

        $hookable->onBefore('get', $listener);

        $hookable->fireBefore('get', []);

        $hookable->fireBefore('get', ['a']);

        $hookable->fireBefore('get', ['a', 'b']);

        $hookable->fireBefore('get', ['a', 'b', 'c']);

        $hookable->fireBefore('get', ['a', 'b', 'c', 'd']);

        $hookable->fireBefore('get', ['a', 'b', 'c', 'd', 'e']);

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
    public function call_after_listeners()
    {
        $hookable = $this->newHookable(['get']);

        $listener = new LoggingCallable();

        $hookable->onAfter('get', $listener);

        $hookable->fireAfter('get', []);

        $hookable->fireAfter('get', ['a']);

        $hookable->fireAfter('get', ['a', 'b']);

        $hookable->fireAfter('get', ['a', 'b', 'c']);

        $hookable->fireAfter('get', ['a', 'b', 'c', 'd']);

        $hookable->fireAfter('get', ['a', 'b', 'c', 'd', 'e']);

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
    public function it_calls_multiple_listeners()
    {
        $hookable = $this->newHookable(['get', 'delete']);

        $listener1 = new LoggingCallable();
        $listener2 = new LoggingCallable();
        $listener3 = new LoggingCallable();
        $listener4 = new LoggingCallable();

        $hookable->onAfter('get', $listener1);
        $hookable->onAfter('get', $listener2);
        $hookable->onAfter('delete', $listener3);
        $hookable->onAfter('delete', $listener4);


        $hookable->fireAfter('get', []);
        $hookable->fireAfter('delete', ['a']);

        $this->assertCount(1, $listener1);
        $this->assertCount(1, $listener2);
        $this->assertCount(1, $listener3);
        $this->assertCount(1, $listener4);

        $this->assertEquals([], $listener1->args(0));
        $this->assertEquals([], $listener2->args(0));
        $this->assertEquals(['a'], $listener3->args(0));
        $this->assertEquals(['a'], $listener4->args(0));
    }

    /**
     * @test
     */
    public function listen_on_unknown_event_throws_exception()
    {
        $hookable = $this->newHookable(['get']);

        $listener = new LoggingCallable();

        $this->expectException(HookNotFoundException::class);
        $hookable->onAfter('save', $listener);
    }

    /**
     * @test
     */
    public function listen_on_unknown_event_throws_no_exception_if_class_does_not_implement_HasMethodHooks()
    {
        $hookable = $this->newHookableWithoutMethodHooks();

        $listener = new LoggingCallable();

        $hookable->onAfter('save', $listener);
    }

    /**
     * @test
     */
    public function listen_on_object_event()
    {
        $hookable = $this->newHookableWithoutMethodHooks();

        $listener = new LoggingCallable();

        $hookable->onAfter(new stdClass(), $listener);

        $event = new stdClass();

        $hookable->fireAfter(stdClass::class, [$event]);
        $this->assertCount(1, $listener);
        $this->assertSame($event, $listener->arg(0));
    }

    /**
     * @test
     */
    public function test_listen_supported_event_class_does_not_throw_exception()
    {
        $hookable = $this->newHookable([stdClass::class]);
        $listener = new LoggingCallable();
        $event = new stdClass();
        $hookable->onAfter($event, $listener);

        $payload = new stdClass();
        $hookable->fireAfter(stdClass::class, [$payload]);

        $this->assertCount(1, $listener);
        $this->assertSame($payload, $listener->arg(0));
    }

    /**
     * @test
     */
    public function test_listen_unsupported_event_class_throws_exception()
    {
        $hookable = $this->newHookable();
        $listener = new LoggingCallable();
        $this->expectException(HookNotFoundException::class);
        $hookable->onAfter(new stdClass(), $listener);
    }

    /**
     * @test
     */
    public function listen_to_multiple_hooks()
    {
        $hookable = $this->newHookable(['get', 'set']);

        $beforeListener = new LoggingCallable();
        $afterListener = new LoggingCallable();


        $hookable->onBefore(['get', 'set'], $beforeListener);
        $hookable->onAfter(['get', 'set'], $afterListener);

        $hookable->fireBefore('get', ['a']);
        $hookable->fireBefore('set', ['b']);

        $hookable->fireAfter('get', ['a']);
        $hookable->fireAfter('set', ['b']);

        $this->assertCount(2, $beforeListener);
        $this->assertCount(2, $afterListener);

        $this->assertEquals('b', $beforeListener->arg(0));
        $this->assertEquals('a', $beforeListener->arg(0, 0));

        $this->assertEquals('b', $afterListener->arg(0));
        $this->assertEquals('a', $afterListener->arg(0, 0));

    }

    protected function newHookable($hooks=[]) : WithMethodHooks
    {
        $object = new WithMethodHooks();
        $object->hooks = $hooks;
        return $object;
    }

    protected function newHookableWithoutMethodHooks() : WithoutMethodHooks
    {
        return new WithoutMethodHooks();
    }
}

class WithoutMethodHooks implements Hookable
{
    use HookableTrait;

    public function fireBefore($event, array $args=[]) : bool
    {
        return $this->callBeforeListeners($event, $args);
    }

    public function fireAfter($event, array $args=[]) : bool
    {
        return $this->callAfterListeners($event, $args);
    }
}

class WithMethodHooks extends WithoutMethodHooks implements HasMethodHooks
{
    public $hooks = [];

    /**
     * Return an array of method names which can be hooked via
     * onBefore and onAfter.
     *
     * @return array
     **/
    public function methodHooks() : array
    {
        return $this->hooks;
    }
}
