<?php
/**
 * Created by PhpStorm.
 * User: michi
 * Date: 09.11.17
 * Time: 09:03
 */

namespace Koansu\DependencyInjection;

use Koansu\DependencyInjection\Contracts\Container as ContainerContract;

class ContainerCallable
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $abstract;

    /**
     * @var string
     */
    protected $method = '';

    /**
     * @var bool
     */
    protected $useAppCall = false;

    /**
     * @var bool
     */
    protected $useParametersInResolve = false;

    /**
     * ContainerCallable constructor.
     *
     * @param ContainerContract $container
     * @param string       $abstract
     * @param string       $method (optional)
     */
    public function __construct(ContainerContract $container, string $abstract, string $method='')
    {
        $this->container = $container;
        $this->abstract = $abstract;
        $this->method = $method;
    }

    /**
     * Call the container or the resolved instance.
     *
     * @return mixed
     */
    public function __invoke()
    {
        $args = func_get_args();

        $instance = $this->container->__invoke(
            $this->abstract,
            $this->useParametersInResolve ? $args : []
        );

        if (!$this->method) {
            return $instance;
        }

        return $this->callMethod($instance, $args);
    }

    /**
     * Determine which method should be called on the resolved instance.
     *
     * @example App::provide(UserController::class)->index()
     *
     * @param string $method
     * @param array $args (optional)
     *
     * @return $this
     */
    public function __call(string $method, array $args=[])
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Determine which method should be called on the resolved instance AND that
     * this method will be called with App::call().
     *
     * @param string $method
     *
     * @return self
     */
    public function call(string $method) : ContainerCallable
    {
        $this->method = $method;
        $this->useAppCall = true;
        return $this;
    }

    /**
     * Determine if the passed parameters to this callable should be forwarded
     * to $app->__invoke().
     * This is mostly not the case, but to have this possibility its added.
     *
     * Example:
     *
     * $provider = $app->provide(MyClass::class);
     * $provider(1,2,3); // This will call $app(MyClass::class) without parameters
     *
     * $provider = $app->provide(MyClass::class)->useParametersInResolve();
     * $provider(1,2,3); // This will call $app(MyClass::class, [1,2,3])
     *
     * @param bool $use
     *
     * @return $this
     */
    public function useParametersInResolve(bool $use=true) : ContainerCallable
    {
        $this->useParametersInResolve = $use;
        return $this;
    }

    /**
     * Return if the container should process the arguments you give in __invoke.
     *
     * @return bool
     */
    public function shouldUseParametersInResolve() : bool
    {
        return $this->useParametersInResolve;
    }

    /**
     * Return the method which should be called after resolving the $abstract.
     *
     * @return string
     */
    public function method() : string
    {
        return $this->method;
    }

    /**
     * Return if this Callable should use App::call() to call the assigned
     * method.
     *
     * @return bool
     */
    public function shouldUseAppCall() : bool
    {
        return $this->useAppCall;
    }

    /**
     * Force this callable to use App::call instead of calling the instance
     * directly.
     *
     * @param bool $use (default:true)
     * @return $this
     */
    public function useAppCall(bool $use=true) : ContainerCallable
    {
        $this->useAppCall = $use;
        return $this;
    }

    /**
     * @param object $instance
     * @param array $args (optional)
     *
     * @return mixed
     */
    protected function callMethod(object $instance, array $args=[])
    {
        if ($this->useAppCall) {
            return $this->container->call([$instance, $this->method], $args);
        }
        return $instance->{$this->method}(...$args);
    }
}