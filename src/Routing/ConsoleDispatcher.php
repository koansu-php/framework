<?php
/**
 *  * Created by mtils on 26.10.2022 at 17:03.
 **/

namespace Koansu\Routing;

use Koansu\Routing\Command;
use Koansu\Routing\Contracts\Dispatcher;
use Koansu\Routing\Exceptions\RouteNotFoundException;
use Koansu\Routing\RouteHit;

use function implode;
use function is_array;

class ConsoleDispatcher implements Dispatcher
{
    /**
     * @var array
     */
    private $routesByPattern = [];

    /**
     * @var string
     */
    private $fallbackCommand = '';

    /**
     * Add a route (definition). Whatever you put into it as $handler you will
     * get returned in match.
     *
     * @param string|array $method
     * @param string $pattern
     * @param mixed $handler
     */
    public function add($method, string $pattern, $handler)
    {
        $this->routesByPattern[$pattern] = $handler;
    }

    /**
     * Find the handler for $method and $uri that someone did add().
     *
     * @param string $method
     * @param string $uri
     *
     * @return RouteHit
     */
    public function match(string $method, string $uri) : RouteHit
    {

        if (!$uri && $this->fallbackCommand) {
            $uri = $this->fallbackCommand;
        }

        if (isset($this->routesByPattern[$uri])) {
            return new RouteHit($method, $uri, $this->routesByPattern[$uri]);
        }

        if (!$uri) {
            throw new RouteNotFoundException("No uri (command) passed and no fallback command set.");
        }

        throw new RouteNotFoundException("No route did match $uri");

    }

    /**
     * Fill the interpreter with route definitions that he did export by toArray()
     *
     * @param array $data
     *
     * @return bool
     */
    public function fill(array $data) : bool
    {
        $casted = [];
        foreach ($data as $pattern=>$info) {
            if (isset($info['command']) && is_array($info['command'])) {
                $info['command']['pattern'] = $pattern;
                $info['command'] = Command::fromArray($info['command']);
            }
            $casted[$pattern] = $info;
        }
        $this->routesByPattern = $casted;
        return true;
    }

    /**
     * Render an uri by the route pattern and parameters.
     *
     * @param string $pattern
     * @param array $parameters (optional)
     *
     * @return string
     */
    public function path(string $pattern, array $parameters = []) : string
    {
        return $pattern . ' ' . implode(' ', $parameters);
    }

    /**
     * {inheritDoc}
     *
     * @return array
     **/
    public function __toArray() : array
    {
        return $this->routesByPattern;
    }

    /**
     * @return string
     */
    public function getFallbackCommand() : string
    {
        return $this->fallbackCommand;
    }

    /**
     * Set a command that will be executed if none was passed.
     * (Something like your command list)
     *
     * @param string $fallbackCommand
     *
     * @return ConsoleDispatcher
     */
    public function setFallbackCommand(string $fallbackCommand) : ConsoleDispatcher
    {
        $this->fallbackCommand = $fallbackCommand;
        return $this;
    }


}