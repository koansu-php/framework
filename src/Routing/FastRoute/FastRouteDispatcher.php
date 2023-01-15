<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:07.
 **/

namespace Koansu\Routing\FastRoute;

use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\Type;
use Koansu\Routing\Exceptions\MethodNotAllowedException;
use Koansu\Routing\Exceptions\RouteNotFoundException;
use Koansu\Routing\Contracts\Dispatcher;
use Koansu\Routing\RouteHit;
use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Routing\CurlyBraceRouteCompiler;
use FastRoute\DataGenerator;
use FastRoute\Dispatcher as FastRouteDispatcherContract;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use FastRoute\RouteParser\Std;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use FastRoute\Dispatcher\CharCountBased as CharCountBasedDispatcher;
use FastRoute\Dispatcher\GroupPosBased as GroupPosBasedDispatcher;
use FastRoute\Dispatcher\MarkBased as MarkBasedDispatcher;
use function implode;


class FastRouteDispatcher implements Dispatcher
{
    /**
     * @var DataGenerator
     */
    protected $dataGenerator;

    /**
     * @var RouteCollector
     */
    protected $collector;

    /**
     * @var FastRouteDispatcherContract
     */
    protected $dispatcher;

    /**
     * @var CurlyBraceRouteCompiler
     */
    protected $compiler;

    /**
     * @var array
     */
    protected $data;

    public function __construct(DataGenerator $dataGenerator=null, CurlyBraceRouteCompiler $compiler=null)
    {
        $this->dataGenerator = $dataGenerator ?: new DataGenerator\GroupCountBased();
        $this->collector = $this->createCollector(new Std(), $this->dataGenerator);
        $this->compiler = $compiler ?: new CurlyBraceRouteCompiler();
    }

    /**
     * {@inheritDoc}
     *
     * @param string|string[] $method
     * @param string          $pattern
     * @param mixed           $handler
     */
    public function add($method, string $pattern, $handler)
    {
        $handler = [
            'handler' => $handler,
            'pattern' => $pattern
        ];
        $this->collector->addRoute($method, $pattern, $handler);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $method
     * @param string $uri
     *
     * @return RouteHit
     */
    public function match(string $method, string $uri) : RouteHit
    {
        $routeInfo = $this->dispatcher()->dispatch($method, $uri);

        if ($routeInfo[0] === FastRouteDispatcherContract::NOT_FOUND) {
            throw new RouteNotFoundException("No route did match uri '$uri'");
        }

        if ($routeInfo[0] === FastRouteDispatcherContract::METHOD_NOT_ALLOWED) {
            $allowedMethods = $routeInfo[1];
            throw new MethodNotAllowedException("Method $method is not allowed on uri '$uri' only " . implode(',', $allowedMethods));
        }

        if ( !isset($routeInfo[1]['handler']) || !isset($routeInfo[1]['pattern'])) {
            throw new DataIntegrityException('The data is broken is missing pattern and handler. Either it was not build by or is broken.');
        }

        $parameters = (isset($routeInfo[2]) && $routeInfo[2]) ? $routeInfo[2] : [];

        return new RouteHit($method, $routeInfo[1]['pattern'], $routeInfo[1]['handler'], $parameters);
    }

    /**
     * {@inheritDoc}
     *
     * @param array $data
     *
     * @return bool
     */
    public function fill(array $data) : bool
    {
        $this->data = $data;
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     **/
    public function __toArray() : array
    {
        return $this->collector->getData();
    }

    /**
     * {@inheritDoc}
     *
     * @param string $pattern
     * @param array $parameters (optional)
     *
     * @return string
     */
    public function path(string $pattern, array $parameters = []) : string
    {
        return $this->compiler->compile($pattern, $parameters);
    }

    /**
     * @return FastRouteDispatcherContract
     */
    protected function dispatcher() : FastRouteDispatcherContract
    {
        if ($this->dispatcher) {
            return $this->dispatcher;
        }
        $this->dispatcher = $this->createDispatcher($this->data ?: $this->collector->getData());
        return $this->dispatcher;
    }

    /**
     * @param RouteParser $parser
     * @param DataGenerator $dataGenerator
     *
     * @return RouteCollector
     */
    protected function createCollector(RouteParser $parser, DataGenerator $dataGenerator) : RouteCollector
    {
        return new RouteCollector($parser, $dataGenerator);
    }

    /**
     * @param array $data
     *
     * @return FastRouteDispatcherContract
     */
    protected function createDispatcher(array $data) : FastRouteDispatcherContract
    {
        if ($this->dataGenerator instanceof DataGenerator\GroupCountBased) {
            return new GroupCountBasedDispatcher($data);
        }

        if ($this->dataGenerator instanceof DataGenerator\CharCountBased) {
            return new CharCountBasedDispatcher($data);
        }

        if ($this->dataGenerator instanceof DataGenerator\GroupPosBased) {
            return new GroupPosBasedDispatcher($data);
        }

        if ($this->dataGenerator instanceof DataGenerator\MarkBased) {
            return new MarkBasedDispatcher($data);
        }

        throw new ImplementationException('Cannot create Dispatcher for DataGenerator ' . Type::of($this->dataGenerator));
    }
}