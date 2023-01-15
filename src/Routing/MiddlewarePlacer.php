<?php
/**
 *  * Created by mtils on 23.10.2022 at 19:47.
 **/

namespace Koansu\Routing;

use Koansu\Routing\Contracts\Input;
use Koansu\Core\Response;

use function array_merge;
use function call_user_func;
use function in_array;

class MiddlewarePlacer
{
    /**
     * @var mixed
     */
    protected $handle;

    /**
     * @var callable
     */
    protected $beforeCallback;

    /**
     * @var callable
     */
    protected $afterCallback;

    /**
     * @var callable
     */
    protected $invoker;

    /**
     * @var callable
     */
    protected $middlewareReplacer;

    /**
     * @var string|callable
     */
    protected $middleware;

    /**
     * Positioner constructor.
     *
     * @param mixed $handle
     * @param callable $beforeCallback
     * @param callable $afterCallback
     * @param callable $invoker
     * @param callable $replacer
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct($handle, callable $beforeCallback, callable $afterCallback, callable $invoker, callable $replacer
    ) {
        $this->handle = $handle;
        $this->beforeCallback = $beforeCallback;
        $this->afterCallback = $afterCallback;
        $this->invoker = $invoker;
        $this->middlewareReplacer = $replacer;
    }

    /**
     * Position the previous added thing before the passed one
     *
     * $list->add('sylvia')->before('thomas')
     *
     * @param mixed $other
     *
     * @return void
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function before($other)
    {
        call_user_func($this->beforeCallback, $this->handle, $other);
    }

    /**
     * Position the previous added thing before the passed one
     *
     * $list->add('thomas')->after('sylvia')
     *
     * @param mixed $other
     *
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    public function after($other)
    {
        call_user_func($this->afterCallback, $this->handle, $other);
    }

    /**
     * @param Input $input
     * @param callable $next
     * @param mixed ...$args
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function __invoke(Input $input, callable $next, ...$args) : Response
    {
        $clientTypes = $this->handle['clientTypes'];
        $scopes = $this->handle['scopes'];

        if ($clientTypes && !in_array($input->getClientType(), $clientTypes)) {
            return $next($input);
        }

        if ($scopes && !in_array((string)$input->getRouteScope(), $scopes)) {
            return $next($input);
        }

        $args = array_merge([$this->handle['middleware'], $input, $next], $args);

        return call_user_func($this->invoker, ...$args);
    }

    /**
     * Apply the added middleware only for this client types
     *
     * @param string ...$type
     *
     * @return $this
     */
    public function clientType(...$type) : MiddlewarePlacer
    {
        $this->handle['clientTypes'] = $type;
        call_user_func($this->middlewareReplacer, $this);
        return $this;
    }

    /**
     * Apply the added middleware only in this scopes.
     *
     * @param string ...$scope
     *
     * @return $this
     */
    public function scope(...$scope)
    {
        $this->handle['scopes'] = $scope;
        call_user_func($this->middlewareReplacer, $this);
        return $this;
    }
}