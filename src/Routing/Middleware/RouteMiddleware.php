<?php
/**
 *  * Created by mtils on 28.10.2022 at 16:28.
 **/

namespace Koansu\Routing\Middleware;

use Koansu\Core\ConstraintParsingTrait;
use Koansu\Core\Exceptions\Termination;
use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Routing\CallableAsInputHandler;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\MiddlewareCollection as MiddlewareCollectionContract;
use Koansu\Core\Exceptions\ConfigurationException;
use Koansu\Core\Response;
use Koansu\Core\CustomFactoryTrait;
use Koansu\Routing\MiddlewareCollection;
use ReflectionException;

/**
 * Class RouteMiddleware
 *
 * This class runs any per route middleware (that was assigned by Route::middleware().
 *
 * @package Ems\Routing
 */
class RouteMiddleware implements SupportsCustomFactory
{
    use ConstraintParsingTrait;
    use CustomFactoryTrait;

    /**
     * @var MiddlewareCollectionContract
     */
    protected $middlewareCollection;

    public function __construct(MiddlewareCollectionContract $middlewareCollection=null)
    {
        $this->middlewareCollection = $middlewareCollection ?: new MiddlewareCollection();
    }

    /**
     * Run the request through the assigned route middlewares.
     *
     * @param Input $input
     * @param callable $next
     * @return Response
     *
     * @throws ReflectionException
     */
    public function __invoke(Input $input, callable $next) : Response
    {
        if (!$input->isRouted()) {
            throw new ConfigurationException('The input has to be routed to get handled by ' . static::class . '.');
        }

        $route = $input->getMatchedRoute();

        $middlewares = $route->middlewares;

        if (!$middlewares) {
            return $next($input);
        }

        $this->configureCollection($middlewares);

        try {
            return $this->middlewareCollection->__invoke($input);
        } catch (Termination $termination) {
            return $next($input);
        }

    }

    protected function configureCollection(array $middlewares)
    {
        $this->middlewareCollection->clear();
        foreach ($middlewares as $middlewareCommand) {
            $constraints = $this->parseConstraint($middlewareCommand);
            foreach ($constraints as $middlewareName=>$parameters) {
                $this->middlewareCollection->add($middlewareCommand, $middlewareName, $parameters);
            }
        }

        $this->middlewareCollection->add('termination', new CallableAsInputHandler(function (Input $input) {
            throw new Termination();
        }));
    }

    /**
     * No normalizing needed here.
     *
     * @param string $name
     *
     * @return string
     **/
    protected function normalizeConstraintName(string $name) : string
    {
        return $name;
    }

}