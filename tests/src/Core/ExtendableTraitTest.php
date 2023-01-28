<?php
/**
 *  * Created by mtils on 04.09.2022 at 08:27.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\Core\ExtendableTrait;
use Koansu\Tests\TestCase;

class ExtendableTraitTest extends TestCase
{
    /**
     * @test
     **/
    public function it_implements_interface()
    {
        $this->assertInstanceOf(Extendable::class, $this->make());
    }

    /**
     * @test
     */
    public function extend_adds_extension()
    {
        $extension = function (...$args) {
            return $args;
        };
        $host = $this->make();
        $this->assertSame([], $host->getExtensionNames());
        $host->extend('return', $extension);
        $this->assertSame(['return'], $host->getExtensionNames());

        $this->assertSame($extension, $host->getExtension('return'));

    }

    /**
     * @test
     **/
    public function remove_removes_extension()
    {
        $extension = function (...$args) {
            return $args;
        };
        $host = $this->make();

        $host->extend('return', $extension);
        $this->assertSame(['return'], $host->getExtensionNames());
        $host->removeExtension('return');
        $this->assertSame([], $host->getExtensionNames());
    }

    /**
     * @test
     **/
    public function get_not_existing_extension_throws_exception()
    {
        $this->expectException(HandlerNotFoundException::class);
        $this->make()->getExtension('foo');
    }

    /**
     * @test
     **/
    public function chain_of_responsibility_works()
    {
        $extensionFoo = function (string $name) {
            return $name === 'foo' ? 'foo-answer' : null;
        };
        $extensionBar = function (string $name) {
            return $name === 'bat' ? 'bar-answer' : null;
        };
        $extensionBoo = function (string $name) {
            return $name === 'boo' ? 'boo-answer' : null;
        };

        $host = $this->make();

        $this->assertNull($host('foo'));

        $host->extend('foo', $extensionFoo);
        $host->extend('bar', $extensionBar);
        $host->extend('boo', $extensionBoo);

        $this->assertEquals('foo-answer', $host('foo'));
    }

    /**
     * @test
     **/
    public function it_throws_exception_if_no_extensions_found_and_it_should_fail()
    {

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionCode(HandlerNotFoundException::NO_HANDLERS_FOUND);

        $this->make()->callOrFail('a');

    }

    /**
     * @test
     **/
    public function it_throws_exception_if_no_extensions_answered_and_it_should_fail()
    {
        $extensionFoo = function (string $name) {
            return $name === 'foo' ? 'foo-answer' : null;
        };
        $extensionBar = function (string $name) {
            return $name === 'bat' ? 'bar-answer' : null;
        };
        $extensionBoo = function (string $name) {
            return $name === 'boo' ? 'boo-answer' : null;
        };

        $host = $this->make();
        $host->extend('foo', $extensionFoo);
        $host->extend('bar', $extensionBar);
        $host->extend('boo', $extensionBoo);

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionCode(HandlerNotFoundException::NO_HANDLER_ANSWERED);

        $host->callOrFail('a');

    }

    /**
     * @test
     **/
    public function handle_pattern_based_extensions()
    {
        $extensionFoo = function (string $name) {
            return "foo:$name";
        };
        $extensionBar = function (string $name) {
            return "bar:$name";
        };
        $extensionBoo = function (string $name) {
            return "boo:$name";
        };

        $host = $this->make();

        $host->extend('users.*', $extensionFoo);
        $host->extend('h?sts.update', $extensionBar);
        $host->extend('contacts.updated', $extensionBoo);

        $this->assertNull($host->callMatching('a'));

        // It collects ALL extensions for users.index but only one will
        // match.
        $host->event('users.index');
        $this->assertEquals('foo:a', $host->callMatching('a'));

        $host->event('hosts.update');
        $this->assertEquals('bar:c', $host->callMatching('c'));

        $host->event('contacts.updated');
        $this->assertEquals('boo:x', $host->callMatching('x'));

        $host->event('clients.deleted');
        $this->assertNull($host->callMatching('x'));

    }

    /**
     * @test
     */
    public function handle_closest_class_matching_extension()
    {
        $extensionFoo = function (string $name) {
            return "foo:$name";
        };
        $extensionBar = function (string $name) {
            return "bar:$name";
        };
        $extensionBoo = function (string $name) {
            return "boo:$name";
        };

        $host = $this->make();

        $host->extend(ExtendableTraitTest_Base::class, $extensionFoo);
        $host->extend(ExtendableTraitTest_Extended::class, $extensionBar);
        $host->extend(ExtendableTraitTest_EvenMoreExtended::class, $extensionBoo);

        $host->event(ExtendableTraitTest_Base::class);
        $this->assertEquals('foo:a', $host->callClosest('a'));
        $this->assertEquals('foo:a', $host->callClosest('a')); // check for cache in code coverage

        $host->event(ExtendableTraitTest_Extended::class);
        $this->assertEquals('bar:a', $host->callClosest('a'));
        $host->event(ExtendableTraitTest_EvenMoreExtended::class);
        $this->assertEquals('boo:a', $host->callClosest('a'));

        $host->removeExtension(ExtendableTraitTest_EvenMoreExtended::class);
        $this->assertEquals('bar:a', $host->callClosest('a'));


        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionCode(HandlerNotFoundException::NO_HANDLERS_FOUND);
        $host->event(self::class);
        $host->callClosest('boom');
    }

    protected function make(string $event='')
    {
        $host = new class() implements Extendable {
            use ExtendableTrait;

            protected $event = '';

            public function __invoke(...$args)
            {
                return $this->callUntilNotNull($this->allExtensions(), $args);
            }
            public function callMatching(...$args)
            {
                return $this->callUntilNotNull($this->extensionsWhoseNameMatches($this->event), $args);
            }
            public function callOrFail(...$args)
            {
                return $this->callUntilNotNull($this->allExtensions(), $args, true);
            }
            public function callClosest(...$args)
            {
                return $this->callUntilNotNull([$this->closestExtensionForClass($this->event, true)], $args);
            }
            public function event(string $event)
            {
                $this->event = $event;
                return $this;
            }
        };
        return $event ? $host->event($event) : $host;
    }
}

class ExtendableTraitTest_Base
{
    //
}

class ExtendableTraitTest_Extended extends ExtendableTraitTest_Base
{
    //
}

class ExtendableTraitTest_EvenMoreExtended extends ExtendableTraitTest_Extended
{
    //
}