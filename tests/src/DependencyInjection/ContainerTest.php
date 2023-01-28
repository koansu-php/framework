<?php

namespace Koansu\Tests\DependencyInjection;

use InvalidArgumentException;
use Koansu\Core\ListenerContainer;
use Koansu\DependencyInjection\Container;
use Koansu\DependencyInjection\ContainerCallable;
use Koansu\DependencyInjection\Contracts\Container as ContainerContract;
use Koansu\DependencyInjection\Exceptions\BindingNotFoundException;
use Koansu\DependencyInjection\Exceptions\ContainerException;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\TestCase;
use RuntimeException;
use stdClass;

use function func_get_args;

class ContainerTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_container_interface()
    {
        $this->assertInstanceOf(Container::class, $this->newContainer());
    }

    /**
     * @test
     */
    public function bind_binds_callables_and_returns_container()
    {
        $container = $this->newContainer();
        $container->bind('foo', function ($app) {});
        $this->assertTrue($container->has('foo'));
    }

    /**
     * @test
     **/
    public function binding_of_not_callable_and_non_string_arg_throws_exception()
    {
        $this->expectException(InvalidArgumentException::class);
        $container = $this->newContainer();
        $container->bind('foo', 43);
    }

    /**
     * @test
     */
    public function has_returns_false_if_binding_doesnt_exist()
    {
        $container = $this->newContainer();
        $this->assertFalse($container->has('foo'));
    }

    /**
     * @test
     */
    public function invoke_calls_binding()
    {
        $container = $this->newContainer();
        $container->bind('foo', function (ContainerContract $container) {
            return $container;
        });

        $this->assertSame($container, $container('foo'));
    }

    /**
     * @test
     */
    public function aliased_invoke_calls_binding()
    {
        $container = $this->newContainer();
        $container->alias('foo', 'bar');
        $container->alias('foo', 'baz');
        $container->bind('foo', function (ContainerContract $container) {
            return $container;
        });

        $this->assertSame($container, $container('bar'));
        $this->assertSame($container, $container('baz'));
    }

    /**
     * @test
     */
    public function get_calls_binding()
    {
        $container = $this->newContainer();
        $container->bind('foo', function (ContainerContract $container) {
            return $container;
        });

        $this->assertSame($container, $container->get('foo'));
    }

    /**
     * @test
     */
    public function get_throws_BindingNotFoundException_if_not_has()
    {
        $this->expectException(BindingNotFoundException::class);
        $this->newContainer()->get('foo');
    }

    /**
     * @test
     */
    public function get_throws_ContainerException_if_anything_goes_wrong()
    {
        $container = $this->newContainer();
        $this->expectException(ContainerException::class);
        $container->bind('foo', function () {
            throw new RuntimeException('Failed');
        });
        $container->get('foo');
    }

    /**
     * @test
     */
    public function provide_returns_callable_which_throws_parameters_away()
    {
        $container = $this->newContainer();
        $container->bind('foo', function (ContainerContract $container) {
            return $container;
        });

        $provider = $container->provide('foo');
        $this->assertFalse($provider->shouldUseParametersInResolve());

        $this->assertInstanceof(ContainerCallable::class, $provider);

        $this->assertSame($container, $provider());
    }

    /**
     * @test
     */
    public function provide_returns_callable_which_passes_parameters()
    {
        $container = $this->newContainer();

        $provider = $container->provide(ContainerTest_ClassParameter::class)->useParametersInResolve();

        $this->assertInstanceof(ContainerCallable::class, $provider);
        $this->assertTrue($provider->shouldUseParametersInResolve());

        $result = $provider('a', 'b', 'c');

        $this->assertInstanceof(ContainerTest_ClassParameter::class, $result);

        $this->assertEquals(['a', 'b', 'c'], $result->args);
    }

    /**
     * @test
     */
    public function provide_returns_callable_for_method_call()
    {
        $container = $this->newContainer();
        $custom = $this->mock(ContainerContract::class);

        $container->bind('foo', function () use ($custom) {
            return $custom;
        });

        $provider = $container->provide('foo')->alias();

        $custom->shouldReceive('alias')
               ->with(1,2)
               ->once()
               ->andReturn('tralala');

        $this->assertEquals('alias', $provider->method());
        $this->assertFalse($provider->shouldUseAppCall());
        $this->assertInstanceof(ContainerCallable::class, $provider);

        $this->assertEquals('tralala', $provider(1, 2));
    }

    /**
     * @test
     */
    public function provide_returns_callable_for_app_method_call()
    {
        $container = $this->newContainer();
        $custom = $this->mock(ContainerContract::class);

        $container->bind('foo', function () use ($custom) {
            return $custom;
        });

        $provider = $container->provide('foo')->alias();
        $provider->useAppCall(true);
        $custom->shouldReceive('alias')
            ->with(1,2)
            ->once()
            ->andReturn('tralala');

        $this->assertEquals('alias', $provider->method());
        $this->assertTrue($provider->shouldUseAppCall());
        $this->assertInstanceof(ContainerCallable::class, $provider);

        $this->assertEquals('tralala', $provider(1, 2));
    }

    /**
     * @test
     */
    public function provide_returns_callable_for_inline_determinism_of_app_call()
    {
        $container = $this->newContainer();
        $custom = $this->mock(ContainerContract::class);

        $container->bind('foo', function () use ($custom) {
            return $custom;
        });

        $provider = $container->provide('foo')->call('alias');

        $custom->shouldReceive('alias')
            ->with(1,2)
            ->once()
            ->andReturn('tralala');

        $this->assertEquals('alias', $provider->method());
        $this->assertTrue($provider->shouldUseAppCall());
        $this->assertInstanceof(ContainerCallable::class, $provider);

        $this->assertEquals('tralala', $provider(1, 2));
    }

    /**
     * @test
     */
    public function invoke_of_shared_binding_returns_same_object()
    {
        $container = $this->newContainer();

        $container->bind('foo', function (ContainerContract $container) {
            return new stdClass();
        }, true);

        $result = $container('foo');

        $this->assertInstanceOf('stdClass', $result);
        $this->assertSame($result, $container('foo'));
        $this->assertSame($result, $container('foo'));

        $this->assertTrue($container->has('foo'));
    }

    /**
     * @test
     */
    public function share_creates_singleton()
    {
        $container = $this->newContainer();

        $concreteClass = ContainerTest_Class::class;
        $concrete = new $concreteClass();
        $factory = new LoggingCallable(function () use ($concrete) {
            return $concrete;
        });

        $container->share(ContainerTest_Interface::class, $factory);

        $this->assertSame($concrete, $container->get(ContainerTest_Interface::class));
        $this->assertCount(1, $factory);
        $this->assertSame($concrete, $container->get(ContainerTest_Interface::class));
        $this->assertCount(1, $factory);
    }

    /**
     * @test
     */
    public function share_does_not_end_in_endless_recursion()
    {
        $container = $this->newContainer();

        $concreteClass = ContainerTest_Class::class;

        $factory = new LoggingCallable(function () use ($concreteClass, $container) {
            return $container->get($concreteClass);
        });

        $container->share(ContainerTest_Interface::class, $factory);

        $concrete = $container->get(ContainerTest_Interface::class);
        $this->assertInstanceOf($concreteClass, $concrete);
        $this->assertCount(1, $factory);
        $this->assertSame($concrete, $container->get(ContainerTest_Interface::class));
        $this->assertCount(1, $factory);
    }

    /**
     * @test
     */
    public function share_does_not_end_in_endless_recursion_when_abstract_and_concrete_is_same()
    {
        $container = $this->newContainer();

        $concreteClass = ContainerTest_Class::class;

        $factory = new LoggingCallable(function () use ($concreteClass, $container) {
            return $container->create($concreteClass);
        });
        $container->share(ContainerTest_Class::class, $factory);

        $this->assertInstanceOf($concreteClass, $container->get(ContainerTest_Class::class));
        $this->assertCount(1, $factory);
        $this->assertInstanceOf($concreteClass, $container->get(ContainerTest_Class::class));
        $this->assertCount(1, $factory);
    }

    /**
     * @test
     */
    public function share_with_interface_and_class()
    {
        $container = $this->newContainer();

        $concreteClass = ContainerTest_Class::class;
        $abstract = ContainerTest_Interface::class;

        $container->share($abstract, $concreteClass);

        $object = $container->get(ContainerTest_Interface::class);

        $this->assertInstanceOf($concreteClass, $object);
        $this->assertSame($object, $container->get(ContainerTest_Interface::class));

    }

    /**
     * @test
     */
    public function share_with_just_a_class()
    {
        $container = $this->newContainer();

        $concreteClass = ContainerTest_Class::class;
        $abstract = ContainerTest_Interface::class;

        $container->share($concreteClass);

        $object = $container->get(ContainerTest_Class::class);

        $this->assertInstanceOf($concreteClass, $object);
        $this->assertSame($object, $container->get(ContainerTest_Class::class));

    }

    /**
     * @test
     */
    public function share_with_just_a_class_and_previously_bound_factory()
    {
        $container = $this->newContainer();

        $concreteClass = ContainerTest_Class::class;

        $container->share($concreteClass);

        $object = $container->get(ContainerTest_Class::class);

        $this->assertInstanceOf($concreteClass, $object);
        $this->assertSame($object, $container->get(ContainerTest_Class::class));

    }

    /**
     * @test
     */
    public function invoke_of_unshared_binding_returns_different_object()
    {
        $container = $this->newContainer();
        $container->bind('foo', function (ContainerContract $container) {
            return new stdClass();
        });

        $result = $container('foo');

        $this->assertInstanceOf('stdClass', $result);
        $this->assertNotSame($result, $container('foo'));
        $this->assertNotSame($result, $container('foo'));
        $this->assertNotSame($result, $container('foo'));
    }

    /**
     * @test
     */
    public function shareInstance_shares_passed_instance()
    {
        $container = $this->newContainer();
        $shared = new stdClass();

        $container->instance('foo', $shared);

        $this->assertSame($shared, $container('foo'));
        $this->assertSame($shared, $container('foo'));

        $this->assertTrue($container->has('foo'));
    }


    /**
     * @test
     */
    public function resolved_and_has_returns_correct_values_on_bind()
    {
        $container = $this->newContainer();

        $this->assertFalse($container->has('foo'));
        $this->assertFalse($container->resolved('foo'));

        $container->bind('foo', function (ContainerContract $container) {
            return new stdClass();
        });

        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->resolved('foo'));

        $result = $container('foo');

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->resolved('foo'));
    }

    /**
     * @test
     */
    public function resolved_and_has_returns_correct_values_on_shared_binding()
    {
        $container = $this->newContainer();

        $this->assertFalse($container->has('foo'));
        $this->assertFalse($container->resolved('foo'));

        $container->bind('foo', function (ContainerContract $container) {
            return new stdClass();
        }, true);

        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->resolved('foo'));

        $result = $container('foo');

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->resolved('foo'));
    }

    /**
     * @test
     */
    public function resolved_and_has_returns_correct_values_on_shared_instance()
    {
        $container = $this->newContainer();

        $this->assertFalse($container->has('foo'));
        $this->assertFalse($container->resolved('foo'));

        $instance = new stdClass();

        $container->instance('foo', $instance);

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->resolved('foo'));

        $result = $container('foo');

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->resolved('foo'));
    }

    /**
     * @test
     */
    public function resolving_listener_gets_called_on_absolute_equal_abstract_and_returns_itself()
    {
        $callable = new LoggingCallable();
        $container = $this->newContainer();

        $container->bind('foo', function (ContainerContract $container) {
            return 'bar';
        });

        $container->on('foo', $callable);

        $this->assertEquals('bar', $container('foo'));

        $this->assertEquals('bar', $callable->arg(0));

        $this->assertCount(1, $callable);
    }

    /**
     * @test
     */
    public function afterResolving_listener_gets_called_on_absolute_equal_abstract_and_returns_itself()
    {
        $callable = new LoggingCallable();
        $container = $this->newContainer();

        $container->on('foo', $callable);
        $container->onAfter('foo', $callable);

        $container->bind('foo', function (ContainerContract $container) {
            return 'bar';
        });

        $this->assertEquals('bar', $container('foo'));

        $this->assertEquals('bar', $callable->arg(0));
        $this->assertSame($container, $callable->arg(1));

        $this->assertCount(2, $callable);
    }

    /**
     * @test
     */
    public function onBefore_on_onAfter_order()
    {
        $callable = new LoggingCallable();
        $container = $this->newContainer();

        $container->onBefore('foo', $callable);
        $container->on('foo', $callable);
        $container->onAfter('foo', $callable);

        $container->bind('foo', function (ContainerContract $container) {
            return 'bar';
        });

        $this->assertEquals('bar', $container('foo'));

        $this->assertEquals('bar', $callable->arg(0));
        $this->assertSame($container, $callable->arg(1));

        $this->assertCount(3, $callable);
    }

    /**
     * @test
     */
    public function on_is_called_if_constructor_is_empty()
    {
        $callable = new LoggingCallable();
        $container = $this->newContainer();

        $container->onBefore(ContainerTest_EmptyConstructor::class, $callable);
        $container->on(ContainerTest_EmptyConstructor::class, $callable);
        $container->onAfter(ContainerTest_EmptyConstructor::class, $callable);

        $object = $container(ContainerTest_EmptyConstructor::class);
        $this->assertInstanceOf(ContainerTest_EmptyConstructor::class, $object);

        $this->assertSame($object, $callable->arg(0, 0));
        $this->assertSame($object, $callable->arg(0, 1));
        $this->assertSame($object, $callable->arg(0, 2));

    }

    /**
     * @test
     */
    public function getListeners_returns_listeners()
    {
        $callable = new LoggingCallable();
        $container = $this->newContainer();

        $this->assertSame([], $container->getListeners('foo'));

        $container->onBefore('foo', $callable);
        $this->assertSame($callable, $container->getListeners('foo', ListenerContainer::BEFORE)[0]);
    }

    /**
     * @test
     */
    public function resolving_listener_gets_called_on_instance_of_abstract()
    {
        $aliasListener = new LoggingCallable();
        $interfaceListener = new LoggingCallable();
        $classListener = new LoggingCallable();
        $class2Listener = new LoggingCallable();
        $otherListener = new LoggingCallable();

        $container = $this->newContainer();

        $container->on('foo', $aliasListener);
        $container->on(ContainerTest_Interface::class, $interfaceListener);
        $container->on(ContainerTest_Class::class, $classListener);
        $container->on(ContainerTest_Class2::class, $class2Listener);
        $container->on( 'Koansu\Core\ContainerTest', $otherListener);

        $container->bind('foo', function (ContainerContract $container) {
            return new ContainerTest_Class2();
        });

        $result = $container('foo');

        $this->assertInstanceOf(ContainerTest_Class2::class, $result);

        $this->assertCount(1, $interfaceListener);
        $this->assertSame($result, $interfaceListener->arg(0));

        $this->assertCount(1, $classListener);
        $this->assertSame($result, $classListener->arg(0));

        $this->assertCount(1, $class2Listener);
        $this->assertSame($result, $class2Listener->arg(0));

        $this->assertCount(0, $otherListener);
    }

    /**
     * @test
     */
    public function resolving_listener_gets_called_on_shareInstance()
    {
        $callable = new LoggingCallable();
        $container = $this->newContainer();

        $instance = new stdClass();

        $container->on('foo', $callable);
        $container->instance('foo', $instance);

        $this->assertSame($instance, $container('foo'));

        $this->assertSame($instance, $callable->arg(0));
        $this->assertCount(1, $callable);
    }

    /**
     * @test
     */
    public function invoke_creates_unbound_classes()
    {
        $class = ContainerTest_Class2::class;
        $this->assertInstanceOf($class, $this->newContainer()->__invoke($class));
    }

    /**
     * @test
     */
    public function invoke_resolves_constructor_parameters()
    {
        $container = $this->newContainer();
        $class = ContainerTest_ClassDependencies::class;

        $interfaceImplementor = new ContainerTest_Class();

        $container->instance(ContainerTest_Interface::class, $interfaceImplementor);

        $result = $container($class);

        $this->assertInstanceOf($class, $result);
        $this->assertSame($interfaceImplementor, $result->interface);
        $this->assertInstanceOf(ContainerTest_Class::class, $result->classObject);
        $this->assertInstanceOf(ContainerTest_Class2::class, $result->class2Object);
    }

    /**
     * @test
     */
    public function invoke_resolves_constructor_parameters_and_overwrites_with_all_positioned_parameters()
    {
        $container = $this->newContainer();
        $class = ContainerTest_ClassDependencies::class;

        $interfaceImplementor = new ContainerTest_Class();

        $classObject = $container(ContainerTest_Class::class);
        $class2Object = $container(ContainerTest_Class2::class);

        $container->instance(ContainerTest_Interface::class, $interfaceImplementor);

        $result = $container($class, [$interfaceImplementor, $classObject, $class2Object]);

        $this->assertInstanceOf($class, $result);
        $this->assertSame($interfaceImplementor, $result->interface);
        $this->assertSame($classObject, $result->classObject);
        $this->assertSame($class2Object, $result->class2Object);
    }

    /**
     * @test
     */
    public function invoke_resolves_constructor_parameters_and_overwrites_with_all_named_parameters()
    {
        $container = $this->newContainer();
        $class = ContainerTest_ClassDependencies::class;

        $interfaceImplementor = new ContainerTest_Class();

        $classObject = $container(ContainerTest_Class::class);
        $class2Object = $container(ContainerTest_Class2::class);


        $result = $container($class, [
            'interface' => $interfaceImplementor,
            'classObject' => $classObject,
            'class2Object' => $class2Object
        ]);

        $this->assertInstanceOf($class, $result);
        $this->assertSame($interfaceImplementor, $result->interface);
        $this->assertSame($classObject, $result->classObject);
        $this->assertSame($class2Object, $result->class2Object);
    }

    /**
     * @test
     */
    public function create_ignores_shared_binding()
    {
        $container = $this->newContainer();
        $container->share(ContainerTest_Class::class);

        $object = $container->create(ContainerTest_Class::class);
        $this->assertInstanceOf( ContainerTest_Class::class, $object);
        $singleton = $container->get(ContainerTest_Class::class);
        $this->assertNotSame($object, $singleton);
        $this->assertSame($singleton, $container->get(ContainerTest_Class::class));
        $this->assertNotSame($object, $container->create(ContainerTest_Class::class));
    }

    /**
     * @test
     */
    public function create_uses_overwritten_class_if_one_was_has()
    {
        $container = $this->newContainer();
        $container->bind(ContainerTest_Class::class, ContainerTest_Class2::class);

        $object = $container->create(ContainerTest_Class::class);
        $this->assertInstanceOf( ContainerTest_Class::class, $object);

    }

    /**
     * @test
     */
    public function create_uses_exact_class_if_forced()
    {
        $container = $this->newContainer();
        $container->bind(ContainerTest_Class::class, ContainerTest_Class2::class);

        $object = $container->create(ContainerTest_Class::class, [], true);
        $this->assertInstanceOf( ContainerTest_Class::class, $object);
        $this->assertFalse($object instanceof ContainerTest_Class2);

    }

    /**
     * @test
     */
    public function bind_string_will_bind_bound_class()
    {
        $container = $this->newContainer();
        $container->bind(ContainerTest_Interface::class, ContainerTest_Class::class);

        $result = $container(ContainerTest_Interface::class);

        $this->assertInstanceOf(ContainerTest_Class::class, $result);
    }

    /**
     * @test
     */
    public function call_injects_dependencies()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();

        $container->instance(ContainerTest_Class::class, $object1);

        $invoke = $container->get(ContainerTest_Class2::class);

        $this->assertSame([$object1], $container->call([$invoke, 'need']));

    }

    /**
     * @test
     */
    public function call_injects_double_dependencies()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();

        $container->instance(ContainerTest_Class::class, $object1);

        $invoke = $container->get(ContainerTest_Class2::class);

        $this->assertSame([$object1, $object1], $container->call([$invoke, 'needDouble']));

    }

    /**
     * @test
     */
    public function call_injects_multiple_dependencies()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();
        $object2 = new ContainerTest_Class2();

        $container->instance(ContainerTest_Class::class, $object1);
        $container->instance(ContainerTest_Interface::class, $object2);

        $invoke = $container->get(ContainerTest_Class2::class);

        $result = $container->call([$invoke, 'needMany']);
        $this->assertCount(2, $result);
        $this->assertSame($object1, $result[0]);
        $this->assertSame($object2, $result[1]);

    }

    /**
     * @test
     */
    public function call_injects_dependencies_and_parameters()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();
        $parameter = 12;

        $container->instance(ContainerTest_Class::class, $object1);

        $invoke = $container->get(ContainerTest_Class2::class);

        $this->assertSame([$object1, $parameter], $container->call([$invoke, 'needParameter'], [$parameter]));

    }

    /**
     * @test
     */
    public function call_injects_multiple_dependencies_and_parameters()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();
        $object2 = new ContainerTest_Class2();
        $parameter1 = 12;
        $parameter2 = 88;

        $container->instance(ContainerTest_Class::class, $object1);
        $container->instance(ContainerTest_Interface::class, $object2);

        $invoke = $container->get(ContainerTest_Class2::class);

        $awaitedArgs = [$object1, $parameter1, $object2, $parameter2];
        $this->assertSame($awaitedArgs, $container->call([$invoke, 'needManyParameters'], [$parameter1, $parameter2]));

    }

    /**
     * @test
     */
    public function call_injects_multiple_dependencies_and_parameters_in_different_order()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();
        $object2 = new ContainerTest_Class2();
        $parameter1 = 12;
        $parameter2 = 88;

        $container->instance(ContainerTest_Class::class, $object1);
        $container->instance(ContainerTest_Interface::class, $object2);

        $invoke = $container->get(ContainerTest_Class2::class);

        $awaitedArgs = [$parameter1, $object1, $parameter2, $object2];
        $this->assertSame($awaitedArgs, $container->call([$invoke, 'needManyParametersReversed'], [$parameter1, $parameter2]));

    }

    /**
     * @test
     */
    public function call_takes_passed_parameters_and_not_injected()
    {
        $container = $this->newContainer();
        $object1 = new ContainerTest_Class();
        $object2 = new ContainerTest_Class2();
        $object3 = new ContainerTest_Class();

        $container->instance(ContainerTest_Class::class, $object1);
        $container->instance(ContainerTest_Interface::class, $object2);

        $invoke = $container->get(ContainerTest_Class2::class);

        $result = $container->call([$invoke, 'needMany'], ['object2' => $object3]);
        $this->assertCount(2, $result);
        $this->assertSame($object1, $result[0]);
        $this->assertSame($object3, $result[1]);

    }

    protected function newContainer() : Container
    {
        return new Container();
    }
}

interface ContainerTest_Interface
{
}

class ContainerTest_Class implements ContainerTest_Interface
{
}

class ContainerTest_Class2 extends ContainerTest_Class
{
    public function need(ContainerTest_Class $object)
    {
        return func_get_args();
    }

    public function needDouble(ContainerTest_Class $object, ContainerTest_Class $object2)
    {
        return func_get_args();
    }

    public function needMany(ContainerTest_Class $object, ContainerTest_Interface $object2)
    {
        return func_get_args();
    }

    public function needParameter(ContainerTest_Class $object, $objectId)
    {
        return func_get_args();
    }

    public function needManyParameters(ContainerTest_Class $object, $objectId, ContainerTest_Interface $object2, $object2Id)
    {
        return func_get_args();
    }

    public function needManyParametersReversed($objectId, ContainerTest_Class $object, $object2Id, ContainerTest_Interface $object2)
    {
        return func_get_args();
    }
}

class ContainerTest_ClassDependencies
{
    public function __construct(ContainerTest_Interface $interface,
                                ContainerTest_Class $classObject,
                                ContainerTest_Class2 $class2Object,
                                $param=null)
    {
        $this->interface = $interface;
        $this->classObject = $classObject;
        $this->class2Object = $class2Object;
        $this->param = $param;
    }
}

class ContainerTest_ClassParameter
{
    public $args;

    public function __construct($a, $b, $c)
    {
        $this->args = func_get_args();
    }
}

class ContainerTest_EmptyConstructor
{
    public function __construct()
    {

    }
}

