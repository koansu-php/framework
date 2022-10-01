<?php

namespace Koansu\DependencyInjection;

use Closure;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\Core\Exceptions\SymbolNotFoundException;
use Koansu\Core\Type;
use LogicException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use UnexpectedValueException;

use function array_merge;
use function call_user_func;
use function class_exists;
use function explode;
use function func_get_args;
use function function_exists;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function spl_object_hash;
use function strpos;

/**
 * This class is a helper class to allow inline evaluation of
 * parameters in dynamic calls like Container::call
 *
 * It also allows you to add additional parameters to your
 * callable which you don't know when registering them.
 *
 * The best way to understand how it is used look into its unit test.
 **/
class Lambda
{

    /**
     * @var string|array|callable
     **/
    protected $call;

    /**
     * @var callable
     */
    protected $callable;

    /**
     * @var ?string
     */
    protected $callClass;

    /**
     * @var object
     */
    protected $callInstance = false;

    /**
     * @var string
     */
    protected $callMethod;

    /**
     * @var bool
     */
    protected $isStaticMethod;

    /**
     * @var bool
     */
    protected $isFunction;

    /**
     * @var bool
     */
    protected $isClosure;

    /**
     * @var callable
     */
    protected $instanceResolver;

    /**
     * @var array
     **/
    protected $additionalArgs = [];

    /**
     * @var array
     **/
    protected $injections = [];

    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @var bool
     */
    protected $autoInject = false;

    /**
     * @var bool
     */
    protected $wasAutoInjected = false;

    /**
     * @var array
     **/
    protected static $reflectionCache = [];

    /**
     * @var array
     */
    protected static $methodSeparators = ['::', '->', '@'];

    /**
     * Create a new Lambda object. Pass something what can be parsed by this
     * class. To resolve classes from class based calls inject a callable to
     * resolve this classes. (An IOCContainer or something similar)
     *
     * @param array|string|callable $call
     * @param callable|null $instanceResolver (optional)
     * @param bool $autoInject (default:false)
     */
    public function __construct($call, callable $instanceResolver=null, bool $autoInject=false)
    {
        if ($call instanceof self) {
            throw new UnexpectedValueException("You cannot make a lambda to forward a lambda.");
        }

        $this->call = $call;
        $this->instanceResolver = $instanceResolver;
        $this->autoInject($autoInject);

    }

    /**
     * Run the lambda code
     *
     * @return mixed
     **/
    public function __invoke()
    {
        $callable = $this->getCallable();

        if ($this->autoInject) {
            return static::callFast([$this, 'trigger'], func_get_args());
        }

        if ($this->injections) {
            return static::callNamed($callable, $this->injections, func_get_args());
        }
        $args = array_merge(func_get_args(), $this->additionalArgs);
        return static::call($callable, $args);
    }

    /**
     * Same as __invoke but prefer to insert the passed arguments at positions
     * were no (auto) injected arguments are.
     *
     * @see self::mergeArguments()
     *
     * @return mixed
     */
    public function trigger()
    {
        $callable = $this->getCallable();

        $args = array_merge(func_get_args(), $this->additionalArgs);

        if ($this->autoInject && !$this->wasAutoInjected) {
            $this->performAutoInjection();
        }

        if (!$this->injections) {
            return static::call($callable, $args);
        }

        $allArgs = Lambda::mergeArguments($callable, $this->injections, $args);

        return static::callFast($callable, $allArgs);
    }

    /**
     * Append some parameters to the passed parameters. They will be injected
     * when the assigned callable is called.
     * Append parameters may also contain Lambdas.
     *
     * @param mixed $parameters
     *
     * @return self
     **/
    public function append($parameters) : Lambda
    {
        $this->additionalArgs = is_array($parameters) ? $parameters : func_get_args();
        return $this;
    }

    /**
     * Same as append, but replace all callable values with lambdas.
     *
     * @param mixed $parameters
     *
     * @return self
     *
     * @see self::append()
     *
     */
    public function curry($parameters) : Lambda
    {
        $parameters = is_array($parameters) ? $parameters : func_get_args();
        return $this->append(static::currify($parameters));
    }

    /**
     * Assign an associative array with a value for each parameter of the callable.
     * The parameters will then be passed to it. (And merged with the normal ones)
     *
     * @param array $injections
     *
     * @return self
     **/
    public function inject(array $injections) : Lambda
    {
        $this->injections = $injections;
        return $this;
    }

    /**
     * @return array
     */
    public function getInjections() : array
    {
        return $this->injections;
    }

    /**
     * This method does allow to skip resolving a type by the assigned factory.
     * It forces to use the assigned object to be passed to the callable.
     *
     * @param string       $abstract an interface or class name.
     * @param object|mixed $instance The actual instance (not closure or factory)
     *
     * @return $this
     */
    public function bind(string $abstract, $instance) : Lambda
    {
        $this->bindings[$abstract] = $instance;
        return $this;
    }

    /**
     * Auto-inject the parameters. If false is passed disable the auto-injection.
     * If $now is true perform the type resolution now and write it
     * into the $injections array.
     *
     * @param bool $autoInject (default:true)
     * @param bool $now (default:false)
     *
     * @return $this
     */
    public function autoInject(bool $autoInject=true, bool $now=false) : Lambda
    {
        $this->autoInject = $autoInject;

        if (!$this->autoInject) {
            return $this;
        }

        if (!$this->instanceResolver) {
            throw new LogicException('You have to assign a instance resolver to support auto injection');
        }

        if ($now) {
            $this->performAutoInjection();
        }

        return $this;
    }

    /**
     * Return true if the parameters should be automatically injected.
     *
     * @return bool
     */
    public function shouldAutoInject() : bool
    {
        return $this->autoInject;
    }

    /**
     * Get the class of the callable. (if there is one)
     *
     * @return string
     */
    public function getCallClass() : ?string
    {
        if ($this->callClass === null) {
            $this->parseCall();
        }
        return $this->callClass;
    }

    /**
     * Get the method|function of the callable (if there is one)
     *
     * @return string
     */
    public function getCallMethod() : string
    {
        if ($this->callMethod === null) {
            $this->parseCall();
        }
        return $this->callMethod;
    }

    /**
     * @return null|object
     */
    public function getCallInstance()
    {
        if ($this->callInstance !== false) {
            return $this->callInstance;
        }

        if (is_array($this->call) && isset($this->call[0]) && is_object($this->call[0])) {
            $this->callInstance = $this->call[0];
            return $this->callInstance;
        }

        if (is_object($this->call) && method_exists($this->call, '__invoke')){
            $this->callInstance = $this->call;
            return $this->callInstance;
        }

        if ($this->isFunction() || $this->isStaticMethod()) {
            $this->callInstance = null;
            return $this->callInstance;
        }

        $this->callInstance = $this->resolveCallInstance($this->getCallClass());

        return $this->callInstance;
    }

    /**
     * Return true if the callable is an instance method. This is true if the
     * there is a class and the method is not static. Also true on __invoke.
     *
     * @return bool
     */
    public function isInstanceMethod() : bool
    {
        return $this->getCallClass() && !$this->isClosure() && !$this->isStaticMethod();
    }

    /**
     * Return true if the callable is a method call and the reflection says it
     * is static.
     *
     * @return bool
     */
    public function isStaticMethod() : bool
    {
        if ($this->isStaticMethod === null) {
            $this->parseCall();
        }
        return $this->isStaticMethod;
    }

    /**
     * Return true if the callable is just a plain function.
     *
     * @return bool
     */
    public function isFunction() : bool
    {
        if ($this->isFunction === null) {
            $this->parseCall();
        }
        return $this->isFunction;
    }

    /**
     * Return true if the callable is a Closure.
     *
     * @return bool
     */
    public function isClosure() : bool
    {
        if ($this->isClosure === null) {
            $this->parseCall();
        }
        return $this->isClosure;
    }

    /**
     * Return the factory to create instances of class names.
     *
     * @return callable|null
     */
    public function getInstanceResolver() : ?callable
    {
        return $this->instanceResolver;
    }


    /**
     * Set the factory that makes the instance out of a class name.
     *
     * @param callable $instanceResolver
     *
     * @return Lambda
     */
    public function setInstanceResolver(callable $instanceResolver) : Lambda
    {
        $this->instanceResolver = $instanceResolver;
        return $this;
    }

    /**
     * This is used to serialize and deserialize the callable of a lambda.
     * It will complain if it is not possible to serialize it.
     * Filled arguments are not concerned!
     *
     * @return string
     *
     * @throws LogicException
     **/
    public function __toString() : string
    {
        if ($this->isClosure()) {
            throw new LogicException('You cannot serialize a closure.');
        }

        if ($this->isFunction()) {
            return $this->getCallMethod();
        }

        $separator = $this->isStaticMethod() ? '::' : '->';

        return $this->getCallClass() . $separator . $this->getCallMethod();

    }

    /**
     * Just call a callable without parsing any lambdas
     *
     * @param callable $callable
     * @param mixed    $args (optional)
     *
     * @return mixed
     **/
    public static function callFast(callable $callable, $args=null)
    {
        return call_user_func($callable, ...(array)$args);
    }

    /**
     * Call a callable and parse its lambdas
     *
     * @param callable $callable
     * @param mixed    $args (optional)
     *
     * @return mixed
     **/
    public static function call(callable $callable, $args=null)
    {

        if (!is_array($args)) {
            $args = $args === null ? [] : [$args];
        }

        return static::callFast($callable, static::evaluateArgs($args));

    }

    /**
     * Calls the passed callable with an array of named parameter
     * ($parameterName=>$value).
     * If you want to pass original parameters to overwrite the injected ones
     * pass them as the third parameter.
     *
     * @see self::toArguments()
     *
     * @param callable $callable
     * @param array    $inject
     * @param array    $callArgs (optional)
     *
     * @return mixed
     **/
    public static function callNamed(callable $callable, array $inject, array $callArgs=[])
    {
        return static::call($callable, static::toArguments($callable, $inject, $callArgs));
    }

    /**
     * Calls the passed callable with an array of named parameter
     * ($parameterName=>$value).
     * If you want to pass additional parameters which gets inserted in every
     * position where an inject key is missing, pass them as the third parameter.
     *
     * @see self::mergeArguments()
     *
     * @param callable $callable
     * @param array    $inject
     * @param array    $callArgs (optional)
     *
     * @return mixed
     */
    public static function callMerged(callable $callable, array $inject, array $callArgs=[])
    {
        return static::call($callable, static::mergeArguments($callable, $inject, $callArgs));
    }

    /**
     * Run all Lambdas in arguments anf return the "parsed"
     *
     * @param array $args
     *
     * @return array
     **/
    public static function evaluateArgs(array $args) : array
    {

        $parsedArgs = [];

        foreach ($args as $key=>$value) {

            if ($value instanceof self) {
                $parsedArgs[$key] = static::callFast($value);
                continue;
            }
            $parsedArgs[$key] = $value;
        }

        return $parsedArgs;

    }

    /**
     * Replace all callable values in an array with lambdas
     *
     * @param array $args
     *
     * @return array
     */
    public static function currify(array $args) : array
    {
        $curried = [];

        foreach ($args as $key=>$value) {
            if (is_callable($value)) {
                $curried[$key] = new static($value);
                continue;
            }
            $curried[$key] = $value;
        }

        return $curried;
    }

    /**
     * Static constructor for shorter instance creation
     *
     * @param string|array|callable $callable
     * @param callable|null         $instanceResolver (optional)
     *
     * @return self
     */
    public static function f($callable, callable $instanceResolver=null) : Lambda
    {
        return new static($callable, $instanceResolver);
    }

    /**
     * Return an array of argument information about $callable.
     * The array is indexed by the name and every value is an array
     * containing information about if its optional and which type
     * it should have.The callable typehint is removed to support
     * protected methods.
     *
     * @param callable|array $callable
     *
     * @return array
     */
    public static function reflect($callable) : array
    {
        $cacheId = static::cacheId($callable);

        if ($reflectArray = static::getFromCache($cacheId)) {
            return $reflectArray;
        }

        $parameters = [];

        foreach(static::getReflection($callable)->getParameters() as $parameter) {
            $type = null;
            if ($classReflection = $parameter->getClass()) {
                $type = $classReflection->getName();
            }
            $parameters[$parameter->getName()] = [
                'optional' => $parameter->isOptional(),
                'type'     => $type
            ];
        }

        return static::putIntoCacheAndReturn($cacheId, $parameters);

    }

    /**
     * Reads all parameter names from the callable and builds a parameter array
     * you can use to call the callable.
     * The second parameter is an associative array with data for each parameter.
     * The third parameter is what you "normally" would pass to the callable.
     * (A numeric array containing parameters)
     * The numeric parameters do overwrite the assoc one. The callable
     * typehint is removed to support protected methods.
     *
     * @param callable|array $callable
     * @param array          $inject
     * @param array          $callArgs (optional)
     *
     * @return array
     **/
    public static function toArguments($callable, array $inject, array $callArgs=[]) : array
    {

        $i = -1;
        $arguments = [];

        foreach(static::reflect($callable) as $name=>$data) {

            $i++;

            // isset() does not work with null, so it would not be possible to
            // overwrite values with null, so instead use array_key_exists
            if (array_key_exists($i, $callArgs)) {
                $arguments[$i] = $callArgs[$i];
                continue;
            }

            if (array_key_exists($name, $inject)) {
                $arguments[$i] = $inject[$name];
                continue;
            }

            if (!$data['optional']) {
                $cacheId = static::cacheId($callable);
                throw new KeyNotFoundException("The parameter $name is required by '$cacheId'. Pass it via inject or the normal parameters.");
            }
        }

        return $arguments;

    }

    /**
     * Same as self::toArguments. But instead of overwriting the $inject
     * parameters with the callArgs they are inserted on any position where
     * an injected value is missing.
     * The typehint is removed to support protected methods.
     *
     * You can pass an assoc array as the first parameter instead of a callable
     * to pass already built reflected parameters.
     *
     * @param callable|array $callable
     * @param array          $inject
     * @param array          $callArgs (optional)
     *
     * @return array
     **/
    public static function mergeArguments($callable, array $inject, array $callArgs=[]) : array
    {
        $arguments = [];

        $reflection = is_array($callable) && !isset($callable[0]) ? $callable : static::reflect($callable);

        foreach ($reflection as $paramName=>$methodData) {
            if (isset($inject[$paramName])) {
                $arguments[] = $inject[$paramName];
                continue;
            }

            $arguments[] = array_shift($callArgs);
        }

        return $arguments;
    }

    /**
     * This method builds the arguments to call a method with its dependencies.
     * You have to build the array with static::reflect($callable) to call this
     * method.
     * Pass the third argument to force a distinct object to be used for a
     * type. For example [CacheInterface::class => $myCustomCache] would lead to
     * not asking the instance resolver and using $myCustomCache.
     *
     * @param array         $reflectedArgs
     * @param callable|null $instanceResolver (optional)
     * @param array         $customBindings (optional)
     *
     * @return array
     */
    public static function buildHintedArguments(array $reflectedArgs, callable $instanceResolver=null, array $customBindings=[]) : array
    {
        $args = [];

        $instanceResolver = $instanceResolver ?: function ($class) { return new $class(); };

        foreach ($reflectedArgs as $name=>$info) {

            if (!$info['type']) {
                continue;
            }

            if (!Type::isCustom($info['type'])) {
                continue;
            }

            if (isset($customBindings[$info['type']])) {
                $args[$name] = $customBindings[$info['type']];
                continue;
            }

            $args[$name] = $instanceResolver($info['type']);
        }

        return $args;

    }

    /**
     * Return all method separators. These are used to split class
     * and method out of a string.
     *
     * @return string[]
     */
    public static function methodSeparators() : array
    {
        return static::$methodSeparators;
    }

    /**
     * Add another supported method separator.
     *
     * @param string $separator
     */
    public static function addMethodSeparator(string $separator)
    {
        static::$methodSeparators[] = $separator;
    }

    /**
     * Get the real callable to pass it to call_user_func.
     *
     * @return callable
     **/
    public function getCallable() : callable
    {
        if (!$this->callable) {
            $this->callable = $this->makeCallable();
        }
        return $this->callable;
    }

    /**
     * Return a cache id for the passed callable
     *
     * @param callable|array $callable
     *
     * @return string
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function cacheId($callable) : string
    {

        if (is_array($callable)) {
            $class = is_string($callable[0]) ? $callable[0] : get_class($callable[0]);
            return "$class::" . $callable[1];
        }

        if (is_string($callable)) {
            return $callable;
        }

        if (!$callable instanceof Closure) {
            /** @noinspection PhpParamsInspection */
            return get_class($callable);
        }

        // A closure will not throw an exception here
        /** @noinspection PhpUnhandledExceptionInspection */
        $r = new ReflectionFunction($callable);
        return 'Closure:' . $r->getFileName() . ':' . $r->getStartLine() . ':' . $r->getEndLine();

    }

    /**
     * This is a small helper to ensure a listener is called only once. So as an
     * example in $app->onAfter() instead of this:
     *
     * $app->onAfter(Repository::class, function (Repository $repository) {
     *   $repository->configure();
     * });
     *
     * You can write:
     * $app->onAfter(Repository::class, Lambda::once(function (Repository $repository) {
     *   $repository->configure();
     * }));
     *
     * @param callable $call
     * @return Closure
     */
    public static function once(callable $call) : Closure
    {
        $received = [];
        return function (...$args) use ($call, &$received) {
            $hash = spl_object_hash($args[0]);
            if (isset($received[$hash])) {
                return;
            }
            $received[$hash] = true;
            $call(...$args);
        };
    }

    /**
     * Make the (eventually not) callable base arg of this class callable.
     *
     * @return callable
     */
    protected function makeCallable()
    {
        if ($this->call instanceof Closure || $this->isFunction()) {
            return $this->call;
        }

        if ($this->isStaticMethod()) {
            return [$this->getCallClass(), $this->getCallMethod()];
        }

        return [$this->getCallInstance(), $this->getCallMethod()];

    }

    /**
     * Parse the call to make it callable and know a few things about it.
     *
     * @throws LogicException
     */
    protected function parseCall()
    {

        if ($this->call instanceof Closure) {
            $this->callClass = Closure::class;
            $this->callMethod = '';
            $this->isStaticMethod = false;
            $this->isFunction = false;
            $this->isClosure = true;
            return;
        }

        list($class, $method) = $this->toClassAndMethod($this->call);

        if ($class === '') {
            $this->callClass = '';
            $this->callMethod = $method;
            $this->isStaticMethod = false;
            $this->isFunction = true;
            $this->isClosure = false;
            return;
        }

        $this->callClass = $class;
        $this->callMethod = $method;
        $this->isFunction = false;
        $this->isClosure = false;

        try {
            $reflection = new ReflectionMethod($class, $method);
            $this->isStaticMethod = $reflection->isStatic();

            if (!$reflection->isPublic()) {
                throw new LogicException("Method '$method' of $class is not public and therefore not callable.");
            }
        } catch (ReflectionException $e) {
            // Class is checked in toClassAndMethod, can only be method
            throw new SymbolNotFoundException([$class, $method], SymbolNotFoundException::METHOD_NOT_FOUND, $e);
        }


    }

    /**
     * @param string|array $call
     *
     * @return string[]
     */
    protected function toClassAndMethod($call) : array
    {
        if (is_array($call)) {
            return is_object($call[0]) ? [get_class($call[0]), $call[1]] : $call;
        }

        if (is_object($call) && method_exists($call, '__invoke')) {
            return [get_class($call), '__invoke'];
        }

        if (function_exists($call)) {
            return ['', $call];
        }

        if (class_exists($call) && method_exists($call, '__invoke')) {
            return [$call, '__invoke'];
        }

        if($separator = static::findMethodSeparator($call)) {
            return static::listClassAndMethod($call, $separator);
        }

        throw new UnexpectedValueException("Cannot parse '$call'.");
    }

    /**
     * Resolve the class of a callable.
     *
     * @param string $class
     *
     * @return object
     */
    protected function resolveCallInstance(string $class) : object
    {
        if (!$this->instanceResolver) {
            return new $class();
        }
        return call_user_func($this->instanceResolver, $class);
    }

    /**
     * Automatically calculate the injections
     */
    protected function performAutoInjection()
    {
        if ($this->wasAutoInjected) {
            return;
        }
        $argumentTypes = static::reflect($this->getCallable());
        $injections = Lambda::buildHintedArguments($argumentTypes, $this->instanceResolver, $this->bindings);
        $this->inject($injections);
        $this->wasAutoInjected = true;
    }

    /**
     * Try to load a reflection from cache.
     *
     * @param string $cacheId
     *
     * @return mixed
     **/
    protected static function getFromCache(string $cacheId)
    {
        return static::$reflectionCache[$cacheId] ?? null;
    }

    /**
     * Store the reflected information about $cacheId and return it
     *
     * @param string $cacheId
     * @param array $reflection
     *
     * @return array
     **/
    protected static function putIntoCacheAndReturn(string $cacheId, array $reflection) : array
    {
        static::$reflectionCache[$cacheId] = $reflection;
        return $reflection;
    }

    /**
     * Get a reflection object for the passed callable. The callable
     * typehint is removed to support protected methods.
     *
     * @param callable|array $callable
     *
     * @return ReflectionFunctionAbstract
     *
     * @throws SymbolNotFoundException
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected static function getReflection($callable)
    {

        if (is_array($callable)) {
            try {
                return new ReflectionMethod($callable[0], $callable[1]);
            } catch (ReflectionException $e) {
                throw new SymbolNotFoundException($callable, SymbolNotFoundException::METHOD_NOT_FOUND, $e);
            }

        }

        if ($callable instanceof Closure) {
            // It is impossible that an existing closure will cause an exception
            // here
            /** @noinspection PhpUnhandledExceptionInspection */
            return new ReflectionFunction($callable);
        }

        if (is_object($callable)) {
            return self::getReflection([$callable, '__invoke']);
        }

        // Should be a string until here
        if ($separator = static::findMethodSeparator($callable)) {
            return self::getReflection(explode($separator, $callable));
        }

        try {
            return new ReflectionFunction($callable);
        } catch (ReflectionException $e) {
            throw new SymbolNotFoundException($callable, SymbolNotFoundException::FUNCTION_NOT_FOUND, $e);
        }

    }

    /**
     * Find the used method separator in $methodString
     *
     * @param string $methodString
     *
     * @return string
     */
    protected static function findMethodSeparator(string $methodString) : string
    {
        foreach (static::$methodSeparators as $separator) {
            if (strpos($methodString, $separator)) {
                return $separator;
            }
        }
        return '';
    }

    /**
     * Return the class and method in 2 array values. If you don't know the
     * separator let it guess it.
     *
     * @param string $methodString
     * @param string $separator (optional)
     *
     * @return array
     */
    protected static function listClassAndMethod(string $methodString, string $separator='') : array
    {
        $separator = $separator ?: static::findMethodSeparator($methodString);
        return explode($separator, $methodString, 2);
    }
}
