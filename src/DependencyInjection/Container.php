<?php

namespace Koansu\DependencyInjection;

use Exception;
use InvalidArgumentException;
use Koansu\Core\ListenerContainer;
use Koansu\DependencyInjection\Contracts\Container as ContainerContract;
use Koansu\DependencyInjection\Exceptions\BindingInstantiationException;
use Koansu\DependencyInjection\Exceptions\BindingNotFoundException;
use Koansu\DependencyInjection\Exceptions\ConcreteClassNotFoundException;
use Koansu\DependencyInjection\Exceptions\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;

use ReflectionNamedType;

use function call_user_func;
use function count;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_string;

class Container implements ContainerContract
{
    /**
     * @var array
     **/
    protected $bindings = [];

    /**
     * @var array
     **/
    protected $aliases = [];

    /**
     * @var array
     **/
    protected $sharedInstances = [];

    /**
     * @var array
     **/
    protected $resolvedAbstracts = [];

    /**
     * @var ListenerContainer
     */
    protected $listeners;

    /**
     * @var array
     */
    protected static $reflectionClasses = [];

    public function __construct()
    {
        $this->listeners = new ListenerContainer();
        $this->instance(ContainerContract::class, $this);
        $this->instance(self::class, $this);
    }

    /**
     * @param string $abstract
     * @param array $parameters (optional)
     *
     * @return object|mixed
     *
     * @throws ContainerException
     * @see Container::__invoke()
     *
     */
    public function __invoke($abstract, array $parameters = [])
    {
        if (!$parameters) {
            return $this->get($abstract);
        }
        return $this->create($abstract, $parameters);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     */
    public function get(string $id)
    {
        if (isset($this->sharedInstances[$id])) {
            return $this->sharedInstances[$id];
        }

        $bound = isset($this->bindings[$id]);

        try {
            $concrete = $this->makeOrCreate($id);
        } catch (Exception $e) {
            if (!$this->has($id)) {
                throw new BindingNotFoundException("Error while creating '$id'. By the way, $id is not bound.", 0, $e);
            }
            throw new ContainerException("Error building $id", 0, $e);
        }

        if ($bound && $this->bindings[$id]['shared']) {
            $this->sharedInstances[$id] = $concrete;
        }

        $this->resolvedAbstracts[$id] = true;

        return $concrete;
    }


    /**
     * {@inheritDoc}
     *
     * @param string $abstract
     * @param array $parameters (optional)
     * @param bool $useExactClass (default: false)
     *
     * @return object
     */
    public function create(string $abstract, array $parameters = [], bool $useExactClass = false) : object
    {
        $implementation = $useExactClass ? $abstract : $this->getAliasOrSame($abstract);

        // Sorry for the try/catch levels of indention but this is so heavy used
        // that I decided to put everything in one method

        if (!$useExactClass && isset($this->bindings[$implementation]) && $this->bindings[$implementation]['concreteClass']) {
            $implementation = class_exists($this->bindings[$implementation]['concreteClass']) ? $this->bindings[$implementation]['concreteClass'] : $implementation;
        }

        if (!isset(self::$reflectionClasses[$implementation])) {
            try {
                self::$reflectionClasses[$implementation] = new ReflectionClass($implementation);
            } catch (ReflectionException $e) {
                throw new ConcreteClassNotFoundException("Unable to instantiate reflection for $abstract", 0, $e);
            }
        }

        if (!$constructor = self::$reflectionClasses[$implementation]->getConstructor()) {
            $concrete = new $implementation($parameters);
            $this->listeners->callByInheritance($abstract, $concrete, [$concrete, $this], ListenerContainer::POSITIONS);
            return $concrete;
        }

        $constructorParams = $constructor->getParameters();

        $callParams = [];

        // All parameters seem to be passed
        if (count($constructorParams) == count($parameters)) {
            try {
                $concrete = self::$reflectionClasses[$implementation]->newInstanceArgs(
                    $parameters
                );
                $this->listeners->callByInheritance(
                    $abstract,
                    $concrete,
                    [$concrete, $this],
                    ListenerContainer::POSITIONS
                );
                return $concrete;
            } catch (ReflectionException $e) {
                throw new BindingInstantiationException("Unable to instantiate $abstract", 0, $e);
            }
        }

        $namedParamsFound = false;

        foreach ($constructorParams as $i=>$param) {

            $name = $param->getName();

            if (isset($parameters[$name])) {
                $callParams[] = $parameters[$name];
                $namedParamsFound = true;
                continue;
            }

            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $className = $type->getName();

            if (isset($parameters[$i]) && $parameters[$i] instanceof $className) {
                $callParams[] = $parameters[$i];
                continue;
            }

            if (!$param->isOptional() || $this->has($className)) {
                $callParams[] = $this->__invoke($className);
            }
        }

        if(!$namedParamsFound) {
            foreach ($parameters as $value) {
                $callParams[] = $value;
            }
        }


        try {
            $object = self::$reflectionClasses[$implementation]->newInstanceArgs(
                $callParams
            );
        } catch (ReflectionException $e) {
            throw new BindingInstantiationException("Unable to instantiate $abstract", 0, $e);
        }

        $this->listeners->callByInheritance($abstract, $object, [$object, $this], ListenerContainer::POSITIONS);

        return $object;

    }

    /**
     * {@inheritdoc}
     *
     * @param string          $abstract
     * @param callable|string $factory
     * @param bool            $singleton (optional)
     *
     * @return void
     **/
    public function bind(string $abstract, $factory, bool $singleton = false)
    {
        $this->storeBinding($abstract, $factory, $singleton);
    }

    /**
     * Create a shared binding (singleton). Omit the factory to let the container
     * create one for you.
     *
     * @param string               $abstract
     * @param callable|string|null $factory
     *
     * @return void
     */
    public function share(string $abstract, $factory=null)
    {
        if (!$factory) {
            $factory = function () use ($abstract) {
                return $this->create($abstract);
            };
        }
        $this->bind($abstract, $factory, true);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $abstract
     * @param string $method (optional)
     *
     * @return ContainerCallable
     **/
    public function provide(string $abstract, string $method = '') : ContainerCallable
    {
        return new ContainerCallable($this, $abstract, $method);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $abstract
     * @param object $instance
     *
     * @return void
     **/
    public function instance(string $abstract, object $instance)
    {
        $this->sharedInstances[$abstract] = $instance;

        // This will never be called, but makes resolved, has etc. easier
        $this->storeBinding($abstract, function () use ($instance) {
            return $instance;
        }, true);

        $this->listeners->callByInheritance($abstract, $instance, [$instance, $this], ListenerContainer::POSITIONS);

    }

    /**
     * {@inheritDoc}
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $abstract
     *
     * @return bool
     **/
    public function resolved(string $abstract) : bool
    {
        return isset($this->resolvedAbstracts[$abstract]) || isset($this->sharedInstances[$abstract]);
    }

    /**
     * {@inheritdoc}
     *
     * You can either pass numeric parameters to insert not injected params
     * positional or pass an assoc array with $parameterName=>$value to manually
     * inject a view parameters on your own.
     * Passing BOTH (numeric and assoc arrays) is not supported.
     *
     * @param callable $callback
     *
     * @return mixed The method result
     */
    public function call(callable $callback, array $parameters = [])
    {

        $argsReflection = Lambda::reflect($callback);
        $args = [];

        foreach ($argsReflection as $name=>$info) {

            // If someone manually added the parameter by name just use that
            if (isset($parameters[$name])) {
                $args[$name] = $parameters[$name];
                continue;
            }

            if (!$info['type']) {
                continue;
            }

            if (!$info['optional']) {
                $args[$name] = $this->get($info['type']);
            }

        }

        // If no args were built and no or numeric parameters were passed
        // Take the fast version
        if (!$args && (isset($parameters[0]) || !$parameters)) {
            return call_user_func($callback, ...$parameters);
        }

        $merged = Lambda::mergeArguments($argsReflection, $args, isset($parameters[0]) ? $parameters : []);
        return Lambda::call($callback, $merged);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $abstract
     * @param string $alias
     *
     * @return void
     **/
    public function alias(string $abstract, string $alias)
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * {@inheritDoc}
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function onBefore($event, callable $listener)
    {
        $this->storeListener($event, $listener, ListenerContainer::BEFORE);
    }

    /**
     * {@inheritDoc}
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function on($event, callable $listener)
    {
        $this->storeListener($event, $listener, ListenerContainer::ON);
    }

    /**
     * {@inheritDoc}
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function onAfter($event, callable $listener)
    {
        $this->storeListener($event, $listener, ListenerContainer::AFTER);
    }

    /**
     * {@inheritDoc}
     *
     * @param string|object $event
     * @param string $position ('after'|'before'|'')
     *
     * @return string[]
     **/
    public function getListeners($event, string $position = '') : array
    {
        if (!$position) {
            return [];
        }
        $abstract = is_object($event) ? get_class($event) : $event;
        return $this->listeners->get($abstract, $position);
    }

    /**
     * @param string $abstract
     *
     * @return object|mixed
     * @throws ReflectionException
     */
    protected function makeOrCreate(string $abstract)
    {
        $abstract = $this->getAliasOrSame($abstract);

        if (!$this->has($abstract)) {
            return $this->create($abstract);
        }

        $object = call_user_func($this->bindings[$abstract]['concrete'], $this);

        $this->listeners->callByInheritance($abstract, $object, [$object, $this], ListenerContainer::POSITIONS);
        return $object;
    }

    /**
     * Stores the binding inside the bindings.
     *
     * @param string          $abstract
     * @param callable|string $concrete
     * @param bool            $shared
     *
     * @return void
     **/
    protected function storeBinding(string $abstract, $concrete, bool $shared)
    {
        $this->bindings[$abstract] = [
            'concrete'      => $this->checkAndReturnCallable($concrete),
            'shared'        => $shared,
            'concreteClass' => is_string($concrete) ? $concrete : null
        ];
    }

    /**
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     * @param string                          $position
     *
     * @return void
     */
    protected function storeListener($event, callable $listener, string $position)
    {
        if (!is_array($event)) {
            $abstract = is_object($event) ? get_class($event) : $event;
            $this->listeners->add($abstract, $listener, $position);
            return;
        }
        foreach ($event as $item) {
            $this->storeListener($item, $listener, $position);
        }

    }

    /**
     * Throws an exception if the arg is not callable.
     *
     * @param callable|string $callback
     *
     * @throws InvalidArgumentException
     *
     * @return callable
     **/
    protected function checkAndReturnCallable($callback)
    {
        if (is_string($callback)) {
            return function ($app) use ($callback) {
                return $app($callback);
            };
        }

        if (!is_callable($callback)) {
            /** @noinspection PhpParamsInspection */
            $type = is_object($callback) ? get_class($callback) : gettype($callback);
            throw new InvalidArgumentException("Passed argument of type $type is not callable");
        }

        return $callback;
    }

    /**
     * @param string $abstract
     *
     * @return string
     */
    protected function getAliasOrSame(string $abstract) : string
    {
        return isset($this->aliases[$abstract]) ? $this->getAliasOrSame($this->aliases[$abstract]) : $abstract;
    }
}
