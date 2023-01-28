<?php
/**
 *  * Created by mtils on 28.10.2022 at 17:44.
 **/

namespace Koansu\Tests;

use Closure;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Route;
use Koansu\Routing\RouteCollector;
use Koansu\Routing\ConsoleDispatcher;
use Koansu\Routing\FastRoute\FastRouteDispatcher;
use Koansu\Routing\GenericInput;
use Koansu\Routing\Router;
use Koansu\Routing\RouteRegistry;

use function in_array;
use function is_bool;
use function is_callable;
use function is_string;
use function str_replace;

trait RoutingTrait
{
    use TestData;
    protected static $testRoutes;
    protected $addRoutes = [];
    protected $addMiddlewares = [];

    /**
     * @param bool|callable $filled
     * @return Router
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function router($filled=false) : Router
    {
        $router = new Router(is_callable($filled) ? $filled : null);
        if ($filled and is_bool($filled)) {
            $registry = $this->registry(true);
            $router->fillDispatchersBy([$registry, 'fillDispatcher']);
            $this->fill($registry);
        }
        return $router;
    }

    protected function registry(bool $filled=false) : RouteRegistry
    {
        $registry = new RouteRegistry();
        if ($filled) {
            $this->fill($registry);
        }
        return $registry;
    }

    /**
     * @beforeClass
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function loadTestRoutes()
    {
        static::$testRoutes = static::includeDataFile('routing/basic-routes.php');
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function fillIfNotFilled($registry, array $controllerReplace=[])
    {
        if (!$registry->getByPattern('users')) {
            $this->fill($registry, $controllerReplace);
        }
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function fill($registry, $controllerReplace=[])
    {
        $registry->register(function (RouteCollector $collector) use ($controllerReplace) {
            $this->fillCollector($collector, $controllerReplace);
        });
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function fillCollector(RouteCollector $collector, $controllerReplace=[])
    {
        foreach (static::$testRoutes as $routeData) {
            $handler = $routeData['handler'];
            if ($controllerReplace && is_string($handler)) {
                $handler = $this->replaceControllerName($handler, $controllerReplace);
            }
            $collector->on($routeData['method'], $routeData['pattern'], $handler)
                ->name($routeData['name']);
        }
    }

    /**
     * @param string $handler
     * @param array $replacements
     *
     * @return string
     */
    protected function replaceControllerName(string $handler, array $replacements) : string
    {
        foreach ($replacements as $search=>$replace) {
            $handler = str_replace($search, $replace, $handler);
        }
        return $handler;
    }

    /**
     * @param Url|string $url
     * @param string $method
     * @param string $clientType
     * @param string $scope
     *
     * @return Input
     */
    protected function routable($url, string $method=Input::GET, string $clientType=Input::CLIENT_WEB, string $scope='default') : Input
    {
        $routable = new GenericInput();
        if (!$url instanceof Url) {
            $url = new Url($url);
        }
        return $routable->setMethod($method)->setUrl($url)->setClientType($clientType)->setRouteScope($scope);
    }

    /**
     * @param Route[] $routes
     * @param string $clientType (optional)
     * @return FastRouteDispatcher
     */
    protected function httpDispatcher(array $routes=[], string $clientType='') : FastRouteDispatcher
    {
        $dispatcher = new FastRouteDispatcher();
        foreach ($routes as $route) {
            if ($clientType && $route->clientTypes && !in_array($clientType, $route->clientTypes)) {
                continue;
            }
            $dispatcher->add($route->methods, $route->pattern, $route->__toArray());
        }
        return $dispatcher;
    }

    /**
     * @param Route[] $routes
     * @return ConsoleDispatcher
     */
    protected function consoleDispatcher(array $routes=[]) : ConsoleDispatcher
    {
        $dispatcher = new ConsoleDispatcher();
        foreach ($routes as $route) {
            if (in_array(Input::CONSOLE, $route->methods)) {
                $dispatcher->add(Input::CONSOLE, $route->pattern, $route->__toArray());
            }

        }
        return $dispatcher;
    }

    protected function dispatcherFactory(array $routes=[]) : Closure
    {
        return function (string $clientType) use ($routes) {
            if (in_array($clientType, [Input::CLIENT_CONSOLE, Input::CLIENT_TASK])) {
                return $this->consoleDispatcher($routes);
            }
            return $this->httpDispatcher($routes, $clientType);
        };
    }

    /**
     * @param Route[] $knownRoutes
     * @param Route[] $routes
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function assertRoutesEquals(array $knownRoutes, array $routes)
    {
        foreach ($knownRoutes as $i=>$route) {
            $this->assertEquals($route->__toArray(), $routes[$i]->__toArray());
        }
    }
}