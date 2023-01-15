<?php
/**
 *  * Created by mtils on 24.10.2022 at 16:21.
 **/

namespace Koansu\Routing;

use ArrayIterator;
use IteratorAggregate;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\Router;

use function array_map;
use function explode;

class RouteCollector implements IteratorAggregate
{
    /**
     * @var Route[]
     */
    protected $routes = [];

    /**
     * @var Command[]
     */
    protected $commands;

    /**
     * @var array
     */
    protected $common = [];

    /**
     * @var string
     */
    public static $methodSeparator = '->';

    /**
     * @var string
     */
    public static $middlewareDelimiter = ':';

    /**
     * RouteCollector constructor.
     *
     * @param array $common
     */
    public function __construct(array $common=[])
    {
        $this->common = $common;
    }

    /**
     * Register a handler for a pattern called by $method(s).
     *
     * @param string|string[] $method
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function on($method, string $pattern, $handler) : Route
    {
        $route = $this->newRoute($method, $this->pattern($pattern), $this->handler($handler));
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Register an handler for a get pattern.
     *
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function get(string $pattern, $handler) : Route
    {
        return $this->on(Input::GET, $pattern, $handler);
    }

    /**
     * Register an handler for a post pattern.
     *
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function post(string $pattern, $handler) : Route
    {
        return $this->on(Input::POST, $pattern, $handler);
    }

    /**
     * Register an handler for a put pattern.
     *
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function put(string $pattern, $handler) : Route
    {
        return $this->on(Input::PUT, $pattern, $handler);
    }

    /**
     * Register an handler for a delete pattern.
     *
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function delete(string $pattern, $handler) : Route
    {
        return $this->on(Input::DELETE, $pattern, $handler);
    }

    /**
     * Register an handler for a patch pattern.
     *
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function patch(string $pattern, $handler) : Route
    {
        return $this->on(Input::PATCH, $pattern, $handler);
    }

    /**
     * Register an handler for a options pattern.
     *
     * @param string $pattern
     * @param mixed $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    public function options(string $pattern, $handler) : Route
    {
        return $this->on(Input::OPTIONS, $pattern, $handler);
    }

    /**
     * Create a console command.
     *
     * @param string $pattern
     * @param mixed $handler
     * @param string $description (optional)
     *
     * @return Command
     * @noinspection PhpMissingParamTypeInspection
     */
    public function command(string $pattern, $handler, string $description='') : Command
    {
        $command = $this->newCommand($pattern, $description);
        $route = $this->on(Input::CONSOLE, $pattern, $handler);
        $route->clientType(Input::CLIENT_CONSOLE);
        $route->name($pattern);
        $route->command($command);
        // The command is also the first argument
        $command->argument('command', 'The command name that should be executed');
        return $command;
    }

    /**
     * Retrieve an external iterator
     *
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return ArrayIterator An instance of an object implementing <b>Iterator</b> or
     *
     * @since 5.0.0
     */
    public function getIterator() : ArrayIterator
    {
        if (!$this->common) {
            return new ArrayIterator($this->routes);
        }
        return new ArrayIterator(
            array_map(
                [$this, 'configureRouteByCommonAttributes'],
                $this->routes
            )
        );
    }

    /**
     * Check if routes were added to this collector
     * @return bool
     */
    public function isEmpty() : bool
    {
        return $this->routes === [];
    }

    /**
     * @param string $method
     * @param string $pattern
     * @param mixed  $handler
     *
     * @return Route
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function newRoute(string $method, string $pattern, $handler) : Route
    {
        return new Route($method, $pattern, $handler, $this);
    }

    /**
     * @param string  $pattern
     * @param string  $description (optional)
     *
     * @return Command
     */
    protected function newCommand(string $pattern, string $description='') : Command
    {
        return new Command($pattern, $description, $this);
    }

    protected function configureRouteByCommonAttributes(Route $route) : Route
    {
        if (isset($this->common[Router::CLIENT]) && !$route->clientTypes) {
            $route->clientType((array)$this->common[Router::CLIENT]);
        }

        if (isset($this->common[Router::SCOPE]) && !$route->scopes) {
            $route->scope((array)$this->common[Router::SCOPE]);
        }

        if (!isset($this->common[Router::MIDDLEWARE])) {
            return $route;
        }

        if ($route->wasMiddlewareRemoved()) {
            return $route;
        }

        $routeMiddlewares = $route->middlewares;

        $route->middleware(); // clear middleware

        $merged = $this->mergeMiddlewares(
            $routeMiddlewares,
            (array)$this->common[Router::MIDDLEWARE]
        );

        $route->middleware($merged);

        return $route;
    }

    /**
     * @param array $routeMiddleware
     * @param array $commonMiddleware
     *
     * @return array
     */
    protected function mergeMiddlewares(array $routeMiddleware, array $commonMiddleware) : array
    {
        $routeMiddleware = $this->middlewareByName($routeMiddleware);
        $commonMiddleware = $this->middlewareByName($commonMiddleware);

        $mergedMiddleware = [];

        foreach ($commonMiddleware as $name=>$parameters) {
            if (isset($routeMiddleware[$name])) {
                $mergedMiddleware[] = $this->signature($name, $routeMiddleware[$name]);
                continue;
            }
            $mergedMiddleware[] = $this->signature($name, $parameters);
        }

        // Now add all middlewares that were not in common middleware
        foreach ($routeMiddleware as $name=>$parameters) {
            if (!isset($commonMiddleware[$name])) {
                $mergedMiddleware[] = $this->signature($name, $parameters);
            }
        }

        return $mergedMiddleware;

    }

    /**
     * @param array $middlewares
     * @return array
     */
    protected function middlewareByName(array $middlewares) : array
    {
        $byName = [];

        foreach ($middlewares as $string) {
            $parts = explode(static::$middlewareDelimiter, $string, 2);
            $byName[$parts[0]] = $parts[1] ?? '';
        }

        return $byName;
    }

    /**
     * Build the middleware signature out of its name and parameters.
     *
     * @param string $name
     * @param string $parameters
     * @return string
     */
    protected function signature(string $name, string $parameters='') : string
    {
        return $parameters ? $name . static::$middlewareDelimiter . $parameters : $name;
    }

    /**
     * @param string $pattern
     *
     * @return string
     */
    protected function pattern(string $pattern) : string
    {
        return isset($this->common[Router::PREFIX]) ? $this->common[Router::PREFIX].$pattern : $pattern;
    }

    /**
     * @param string|mixed $handler
     *
     * @return string|object
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function handler($handler)
    {
        if (!is_string($handler)) {
            return $handler;
        }
        return isset($this->common[Router::CONTROLLER]) ? $this->common[Router::CONTROLLER].static::$methodSeparator.$handler : $handler;
    }
}