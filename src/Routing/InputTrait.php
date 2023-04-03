<?php
/**
 *  * Created by mtils on 25.10.2022 at 15:18.
 **/

namespace Koansu\Routing;

use ArrayAccess;
use Koansu\Core\None;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;
use stdClass;

trait InputTrait
{
    /**
     * @var Url
     */
    protected $url;

    /**
     * @var RouteScope|object|string
     */
    protected $routeScope;

    /**
     * @var string
     */
    protected $clientType = '';

    /**
     * @var Route
     */
    protected $matchedRoute;

    /**
     * @var array|ArrayAccess
     */
    protected $routeParameters = [];

    /**
     * @var callable
     */
    protected $handler;

    /**
     * @var string
     */
    protected $locale = '';

    /**
     * @var string
     */
    protected $determinedContentType = '';

    /**
     * @var string
     */
    protected $apiVersion = '';

    /**
     * @var object
     */
    protected $user;

    /**
     * @return Url
     **/
    public function getUrl() : Url
    {
        if (!$this->url) {
            $this->url = new Url();
        }
        return $this->url;
    }

    /**
     * Return the access method (get, post, console, scheduled,...)
     *
     * @return string
     **/
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getClientType() : string
    {
        return $this->clientType;
    }

    /**
     * @return RouteScope|object|string|null
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getRouteScope()
    {
        return $this->routeScope;
    }

    /**
     * @return Route|null
     */
    public function getMatchedRoute() : ?Route
    {
        return $this->matchedRoute;
    }

    /**
     * @param Route $route
     * @param callable $handler
     * @param array $parameters
     * @return Input
     */
    public function makeRouted(Route $route, callable $handler, array $parameters=[]) : Input
    {
        return $this->replicate([
                                    'matchedRoute'      => $route,
                                    'handler'           => $handler,
                                    'routeParameters'   => $parameters
                                ]);
    }

    /**
     * @return ArrayAccess|array
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getRouteParameters()
    {
        return $this->routeParameters;
    }

    /**
     * Return the actual handler
     *
     * @return callable|null
     */
    public function getHandler() : ?callable
    {
        return $this->handler;
    }

    /**
     * Returns true if this object is routed.
     *
     * @return bool
     */
    public function isRouted() : bool
    {
        return $this->matchedRoute && $this->handler;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getDeterminedContentType() : string
    {
        return $this->determinedContentType;
    }

    /**
     * @return string
     */
    public function getApiVersion() : string
    {
        return $this->apiVersion;
    }

    /**
     * {@inheritDoc}
     *
     * @return object
     */
    public function getUser() : object
    {
        if (!$this->user) {
            return new stdClass();
        }
        return $this->user;
    }

    /**
     * @param RouteScope|string|null $scope
     * @return Input
     * @noinspection PhpMissingParamTypeInspection
     */
    public function withRouteScope($scope) : Input
    {
        return $this->replicate(['routeScope' => $scope]);
    }

    /**
     * @param string $locale
     * @return Input
     */
    public function withLocale(string $locale) : Input
    {
        return $this->replicate(['locale' => $locale]);
    }

    /**
     * Assign a "current" user for this input.
     *
     * @param object $user
     * @return Input
     */
    public function withUser(object $user) : Input
    {
        $copy = clone $this;
        $copy->user = $user;
        return $copy;
    }

    /**
     * @param string $contentType
     * @return Input
     */
    public function withDeterminedContentType(string $contentType) : Input
    {
        return $this->replicate(['determinedContentType' => $contentType]);
    }

    /**
     * @param string $key
     * @return array|ArrayAccess|callable|None|Url|Route|RouteScope|string|null
     */
    protected function getInputTraitProperty(string $key)
    {
        switch ($key) {
            case 'url':
                return $this->getUrl();
            case 'routeScope':
                return $this->getRouteScope();
            case 'method':
                return $this->getMethod();
            case 'clientType':
                return $this->getClientType();
            case 'matchedRoute':
                return $this->getMatchedRoute();
            case 'routeParameters':
                return $this->getRouteParameters();
            case 'handler':
                return $this->getHandler();
            case 'locale':
                return $this->getLocale();
            case 'determinedContentType':
                return $this->getDeterminedContentType();
            case 'apiVersion':
                return $this->getApiVersion();
        }
        return new None();
    }

    /**
     * @param string|RouteScope|object $scope
     * @return void
     */
    protected function applyRouteScope($scope)
    {
        $this->routeScope = $scope;
    }

    /**
     * Helper method for getFrom() if second parameter is an array
     *
     * @param string $from
     * @param string[] $keys
     *
     * @return array
     */
    protected function collectFrom(string $from, array $keys) : array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->getFrom($from, $key);
        }
        return $results;
    }
}