<?php

namespace Koansu\Tests\DependencyInjection;

use Closure;
use DateTime;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\Core\ListenerContainer;
use Koansu\DependencyInjection\Lambda;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\Fakes\NamedObject;
use Koansu\Tests\TestCase;
use LogicException;
use stdClass;
use UnexpectedValueException;

use function func_get_args;

class LambdaTest extends TestCase
{
    /**
     * @test
     */
    public function constructors()
    {
        $this->assertInstanceOf(
            Lambda::class,
            new Lambda($this->f())
        );
        $this->assertInstanceOf(
            Lambda::class,
            Lambda::f($this->f())
        );
    }

    /**
     * @test
     **/
    public function constructing_with_lambda_throws_exception()
    {
        $lambda = $this->lambda();
        $this->expectException(UnexpectedValueException::class);
        new Lambda($lambda);
    }

    /**
     * @test
     */
    public function invoke_calls_passed_callable()
    {
        $f = new LoggingCallable;
        $lambda = Lambda::f($f);

        $lambda();
        $this->assertCount(1, $f);
        $this->assertCount(0, $f->args(0));

        $f = new LoggingCallable;
        $lambda = Lambda::f($f);

        $lambda('a');
        $this->assertCount(1, $f);
        $this->assertCount(1, $f->args(0));
        $this->assertEquals('a', $f->arg(0));

        $f = new LoggingCallable;
        $lambda = Lambda::f($f);

        $lambda('a', 'b');
        $this->assertCount(1, $f);
        $this->assertCount(2, $f->args(0));
        $this->assertEquals('a', $f->arg(0));
        $this->assertEquals('b', $f->arg(1));

        $f = new LoggingCallable;
        $lambda = Lambda::f($f);

        $lambda('a', 'b', 'c');
        $this->assertCount(1, $f);
        $this->assertCount(3, $f->args(0));
        $this->assertEquals('a', $f->arg(0));
        $this->assertEquals('b', $f->arg(1));
        $this->assertEquals('c', $f->arg(2));

        $f = new LoggingCallable;
        $lambda = Lambda::f($f);

        $lambda('a', 'b', 'c', 'd');
        $this->assertCount(1, $f);
        $this->assertCount(4, $f->args(0));
        $this->assertEquals('a', $f->arg(0));
        $this->assertEquals('b', $f->arg(1));
        $this->assertEquals('c', $f->arg(2));
        $this->assertEquals('d', $f->arg(3));

        $f = new LoggingCallable;
        $lambda = Lambda::f($f);

        $lambda('a', 'b', 'c', 'd', 'e');
        $this->assertCount(1, $f);
        $this->assertCount(5, $f->args(0));
        $this->assertEquals('a', $f->arg(0));
        $this->assertEquals('b', $f->arg(1));
        $this->assertEquals('c', $f->arg(2));
        $this->assertEquals('d', $f->arg(3));
        $this->assertEquals('e', $f->arg(4));
    }

    /**
     * @test
     */
    public function invoke_appends_appended_parameters()
    {
        $f = new LoggingCallable;
        $lambda = Lambda::f($f)->append('a');

        $lambda();
        $this->assertCount(1, $f);
        $this->assertCount(1, $f->args(0));
        $this->assertEquals('a', $f->arg(0));

        $f = new LoggingCallable;
        $lambda = Lambda::f($f)->append('b');

        $lambda('a');
        $this->assertCount(1, $f);
        $this->assertCount(2, $f->args(0));
        $this->assertEquals('a', $f->arg(0));
        $this->assertEquals('b', $f->arg(1));

    }

    /**
     * @test
     */
    public function curry_evaluates_callable_parameters()
    {
        $f = new LoggingCallable;
        $lambda = Lambda::f($f)->curry(function () {
            return 'foo';
        });

        $lambda();
        $this->assertCount(1, $f);
        $this->assertCount(1, $f->args(0));
        $this->assertEquals('foo', $f->arg(0));

        $f = new LoggingCallable;

        $lambda = Lambda::f($f)->curry('b', function () {
            return 'c';
        });

        $lambda('a');
        $this->assertCount(1, $f);
        $this->assertCount(3, $f->args(0));
        $this->assertEquals('a', $f->arg(0));
        $this->assertEquals('b', $f->arg(1));
        $this->assertEquals('c', $f->arg(2));

    }

    /**
     * @test
     */
    public function call_supports_scalar_parameter()
    {
        $f = new LoggingCallable;
        Lambda::call($f);
        $this->assertCount(1, $f);
        $this->assertCount(0, $f->args());

        $f = new LoggingCallable;
        Lambda::call($f, 'a');
        $this->assertCount(1, $f);
        $this->assertCount(1, $f->args());
        $this->assertEquals('a', $f->arg(0));
    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_closure()
    {

        $r = Lambda::reflect(function () {});
        $this->assertEquals([], $r);


        $r = Lambda::reflect(function ($a, $b=null) {});
        $awaited = [
            'a' => ['optional' => false, 'type' => null],
            'b' => ['optional' => true , 'type' => null],
        ];

        $this->assertEquals($awaited, $r);

    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_closure_by_cache()
    {
        $f = function ($a, $b=null) {};

        $r = Lambda::reflect($f);
        $awaited = [
            'a' => ['optional' => false, 'type' => null],
            'b' => ['optional' => true , 'type' => null],
        ];

        $this->assertEquals($awaited, $r);

        // Cache hit (only seen in code coverage analysis)
        /** @noinspection PhpUnusedLocalVariableInspection */
        $r = Lambda::reflect($f);

    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_invokable()
    {

        $r = Lambda::reflect(new LambdaTestInvokable);

        $awaited = [
            'a' => ['optional' => false, 'type' => null],
            'b' => ['optional' => true , 'type' => 'DateTime'],
        ];

        $this->assertEquals($awaited, $r);

    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_instance_array()
    {

        $instance = new LambdaTestObject;

        $r = Lambda::reflect([$instance, 'process']);

        $awaited = [
            'object' => ['optional' => false, 'type' => NamedObject::class],
            'url'    => ['optional' => false, 'type' => ListenerContainer::class],
            'split'  => ['optional' => true, 'type' => null],
        ];

        $this->assertEquals($awaited, $r);

    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_class_string_array()
    {

        $r = Lambda::reflect([LambdaTestObject::class, 'check']);

        $awaited = [
            'user'   => ['optional' => false, 'type' => null]
        ];

        $this->assertEquals($awaited, $r);

    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_class_and_method_string()
    {

        $r = Lambda::reflect(LambdaTestObject::class.'::uncheck');

        $awaited = [
            'user'   => ['optional' => false, 'type' => null]
        ];

        $this->assertEquals($awaited, $r);

    }

    /**
     * @test
     */
    public function reflect_returns_arguments_of_function_string()
    {

        $r = Lambda::reflect('Koansu\Tests\DependencyInjection\testFunction');

        $awaited = [
            'a'   => ['optional' => false, 'type' => null]
        ];

        $this->assertEquals($awaited, $r);

    }

    /**
     * @test
     */
    public function toArguments_builds_arguments_for_closure()
    {

        $f = function ($a, $b, $c=null) {};

        $params = ['a' => 'foo', 'b' => 'bar', 'c' => 'acme'];

        $awaited = array_values($params);

        $this->assertEquals($awaited, Lambda::toArguments($f, $params));

    }

    /**
     * @test
     */
    public function toArguments_builds_arguments_for_closure_with_callArgs()
    {

        $f = function ($a, $b, $c=null) {};

        $params = ['a' => 'foo', 'b' => 'bar', 'c' => 'acme'];

        $awaited = ['joe', 'jill', 'acme'];

        $this->assertEquals($awaited, Lambda::toArguments($f, $params, ['joe', 'jill']));

    }

    /**
     * @test
     */
    public function toArguments_builds_arguments_for_closure_with_callArgs_with_null_values()
    {

        $f = function ($a, $b, $c=null) {};

        $params = ['a' => 'foo', 'b' => 'bar', 'c' => 'acme'];

        $awaited = ['joe', null, 'acme'];

        $this->assertEquals($awaited, Lambda::toArguments($f, $params, ['joe', null]));

    }

    /**
     * @test
     */
    public function toArguments_throws_no_exception_if_optional_parameter_is_not_passed()
    {

        $f = function ($a, $b, $c=null) {};

        $params = ['a' => 'foo', 'b' => 'bar'];

        $awaited = ['joe', 'bar'];

        $this->assertEquals($awaited, Lambda::toArguments($f, $params, ['joe']));

    }

    /**
     * @test
     **/
    public function toArguments_throws_exception_if_optional_parameter_is_not_passed()
    {

        $f = function ($a, $b, $c=null) {};

        $params = ['a' => 'foo', 'c' => 'bar'];

        $this->expectException(KeyNotFoundException::class);
        Lambda::toArguments($f, $params, ['joe']);

    }

    /**
     * @test
     */
    public function callNamed_calls_closure_with_passed_parameters()
    {

        $f = function ($a, $b, $c=null) { return func_get_args(); };

        $params = ['a' => 'foo', 'b' => 'bar'];

        $awaited = ['joe', 'bar'];

        $this->assertEquals($awaited, Lambda::callNamed($f, $params, ['joe']));

    }

    /**
     * @test
     */
    public function inject_calls_closure_with_passed_parameters()
    {

        $f = Lambda::f(function ($a, $b, $c=null) { return func_get_args(); })
                   ->inject(['a' => 'foo', 'b' => 'bar']);

        $awaited = ['joe', 'bar'];

        $this->assertEquals($awaited, $f('joe'));

    }

    /**
     * @test
     */
    public function examine_a_closure()
    {
        $closure = function () {};
        $f = $this->lambda($closure);

        $this->assertEquals(Closure::class, $f->getCallClass());
        $this->assertEquals('', $f->getCallMethod());
        $this->assertSame($closure, $f->getCallInstance());
        $this->assertFalse($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertTrue($f->isClosure());
    }

    /**
     * @test
     */
    public function examine_an_instance_method()
    {

        $obj = new LambdaTestObject();

        $f = $this->lambda([$obj, 'process']);
        $this->assertEquals(LambdaTestObject::class, $f->getCallClass());

        $this->assertEquals('process', $f->getCallMethod());
        $this->assertSame($obj, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

    }

    /**
     * @test
     */
    public function examine_a_static_method()
    {

        $f = $this->lambda([LambdaTestObject::class, 'check']);
        $this->assertEquals(LambdaTestObject::class, $f->getCallClass());

        $this->assertEquals('check', $f->getCallMethod());
        $this->assertNull($f->getCallInstance());
        $this->assertFalse($f->isInstanceMethod());
        $this->assertTrue($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

    }

    /**
     * @test
     */
    public function examine_an_instance_method_of_passed_class()
    {

        $f = $this->lambda([LambdaTestObject::class, 'process']);
        $this->assertEquals(LambdaTestObject::class, $f->getCallClass());

        $this->assertEquals('process', $f->getCallMethod());
        $this->assertInstanceOf(LambdaTestObject::class, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

    }

    /**
     * @test
     */
    public function examine_an_instance_method_of_passed_class_string()
    {

        $f = $this->lambda(LambdaTestObject::class.'::process');

        $this->assertEquals(LambdaTestObject::class, $f->getCallClass());

        $this->assertEquals('process', $f->getCallMethod());
        $this->assertInstanceOf(LambdaTestObject::class, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

        $f = $this->lambda(LambdaTestObject::class.'->process');

        $this->assertEquals(LambdaTestObject::class, $f->getCallClass());

        $this->assertEquals('process', $f->getCallMethod());
        $this->assertInstanceOf(LambdaTestObject::class, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

        $f = $this->lambda(LambdaTestObject::class.'@process');

        $this->assertEquals(LambdaTestObject::class, $f->getCallClass());

        $this->assertEquals('process', $f->getCallMethod());
        $this->assertInstanceOf(LambdaTestObject::class, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());
    }

    /**
     * @test
     */
    public function examine_an_invokable_class()
    {

        $f = $this->lambda(LambdaTestInvokable::class);
        $this->assertEquals(LambdaTestInvokable::class, $f->getCallClass());

        $this->assertEquals('__invoke', $f->getCallMethod());
        $this->assertInstanceOf(LambdaTestInvokable::class, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

    }

    /**
     * @test
     */
    public function examine_an_invokable_instance()
    {

        $i = new LambdaTestInvokable();
        $f = $this->lambda($i);
        $this->assertEquals(LambdaTestInvokable::class, $f->getCallClass());

        $this->assertEquals('__invoke', $f->getCallMethod());
        $this->assertSame($i, $f->getCallInstance());
        $this->assertTrue($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertFalse($f->isFunction());
        $this->assertFalse($f->isClosure());

    }

    /**
     * @test
     */
    public function test_passing_not_public_method_throws_exception()
    {
        $this->expectException(LogicException::class);
        $f = $this->lambda([static::class, 'f']);
        $f->isClosure();
    }

    /**
     * @test
     */
    public function examine_a_function()
    {

        $f = $this->lambda('str_replace');
        $this->assertEquals('', $f->getCallClass());

        $this->assertEquals('str_replace', $f->getCallMethod());
        $this->assertNull($f->getCallInstance());
        $this->assertFalse($f->isInstanceMethod());
        $this->assertFalse($f->isStaticMethod());
        $this->assertTrue($f->isFunction());
        $this->assertFalse($f->isClosure());

    }

    /**
     * @test
     */
    public function call_instance_method()
    {
        $f = $this->lambda(LambdaTestObject::class.'::run');

        $this->assertEquals([1,2,3], $f(1,2,3));
    }

    /**
     * @test
     */
    public function call_static_method()
    {
        $f = $this->lambda(LambdaTestObject::class.'::runStatic');

        $this->assertEquals([1,2,3], $f(1,2,3));
    }

    /**
     * @test
     */
    public function call_with_unknown_syntax_throws_exception()
    {
        $f = $this->lambda(LambdaTestObject::class.'||runStatic');
        $this->expectException(UnexpectedValueException::class);
        $f(1,2,3);
    }

    /**
     * @test
     */
    public function call_with_custom_factory()
    {
        $factory = function () {
            return new stdClass();
        };

        $f = Lambda::f(LambdaTestObject::class.'@run', $factory);

        $this->assertInstanceOf(stdClass::class, $f->getCallInstance());
    }

    /**
     * @test
     */
    public function method_separators()
    {
        Lambda::addMethodSeparator('|||');
        $this->assertContains('|||', Lambda::methodSeparators());
    }

    /**
     * @test
     */
    public function get_and_set_instanceResolver()
    {
        $f = $this->lambda(LambdaTestObject::class.'->process');
        $resolver = function ($class) {
            return (object)['clazz' => $class];
        };
        $this->assertSame($f, $f->setInstanceResolver($resolver));
        $this->assertSame($resolver, $f->getInstanceResolver());
        $this->assertInstanceOf(stdClass::class, $f->getCallInstance());
        $this->assertEquals(LambdaTestObject::class, $f->getCallInstance()->clazz);

    }

    protected function lambda($callable=null) : Lambda
    {
        return new Lambda($callable ?: function () {});
    }

    protected function f() : Closure
    {
        return function () {};
    }

    public function foo() : string
    {
        return 'bar';
    }
}

class LambdaTestInvokable
{
    public function __invoke($a, DateTime $b=null)
    {
    }
}

class LambdaTestObject
{
    public function process(NamedObject $object, ListenerContainer $url, $split=false )
    {
    }

    public function run() : array
    {
        return func_get_args();
    }

    /** @noinspection PhpUnused */
    public static function runStatic() : array
    {
        return func_get_args();
    }

    public static function check($user)
    {
    }

    /** @noinspection PhpUnused */
    public static function uncheck($user)
    {
    }
}

function testFunction($a)
{
}
