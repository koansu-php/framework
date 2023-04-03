<?php
/**
 *  * Created by mtils on 26.10.2022 at 18:27.
 **/

namespace Koansu\Routing\Contracts;

use ArrayAccess;
use Countable;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\SupportsCustomFactory;
use IteratorAggregate;
use Koansu\Routing\MiddlewarePlacer;

/**
 * Interface MiddlewareCollection
 *
 * The MiddlewareCollection is a middleware stack. The array interface behaves
 * so that it returns $name=>$middleware.
 *
 * Middleware is either callable or a string (class/ioc container binding key).
 * Every middleware has to be named. Then you have control over the sequence by
 * using the before/after methods.
 * The name also allows to add the same class multiple times (with different
 * parameters)
 *
 * The "main input handler" has to be an instance of Ems\Contracts\Routing\InputHandler.
 * If some handler returns a response the MiddlewareCollection must skip all
 * handlers that implement this interface.
 *
 * You can add as many InputHandler objects as handlers as you want. The middleware
 * will skip just all of them after receiving a response from any handler.
 * Just to clear this up: The middleware does not have to be an instance of
 * InputHandler to return responses. It just marks itself as "I am not processing
 * the response, if no $next parameter". And due to this fact the middleware stack
 * knows that it must not call it after receiving a response.
 *
 * Use parameters($name) to get the assigned parameters.
 *
 * To position a middleware call add($name, $middleware)->before($otherName)
 * or add($name, $middleware)->after($otherName)
 *
 * Middleware signature is:
 *
 * function (Input $input, callable $next) {
 *
 * }
 *
 * If you support custom parameters just add it to the signature:
 *
 * function (Input $input, callable $next, $role, ...) {
 *
 * }
 *
 * If you want to work with the response call next middleware and work with the
 * response:
 *
 * function (Input $input, callable $next) {
 *     $response = $next($input);
 *     return $response;
 * }
 *
 * Otherwise, just manipulate the $input.
 *
 * @package Ems\Contracts\Routing
 */
interface MiddlewareCollection extends InputHandler, ArrayAccess, Arrayable, Countable, IteratorAggregate, SupportsCustomFactory
{
    /**
     * Add a middleware to the stack. Add the main handler by adding an InputHandler
     * object.
     *
     * @param string                        $name
     * @param callable|string|InputHandler  $middleware
     * @param string|array                  $parameters (optional)
     *
     * @return MiddlewarePlacer
     * @noinspection PhpMissingParamTypeInspection
     */
    public function add(string $name, $middleware, $parameters=null) : MiddlewarePlacer;

    /**
     * Return the middleware instance named $name. In opposite to offsetGet()
     * this method will make the instance if this is necessary. (If a string was
     * assigned). offsetGet() would just return the string.
     *
     * @param string $name
     *
     * @return callable
     */
    public function middleware(string $name) : callable;

    /**
     * Return the assigned parameters for middleware named $name.
     * @param string $name
     *
     * @return array
     */
    public function parameters(string $name) : array;
}