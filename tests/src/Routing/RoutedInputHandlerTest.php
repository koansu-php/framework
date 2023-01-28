<?php
/**
 *  * Created by mtils on 28.10.2022 at 16:12.
 **/

namespace Koansu\Tests\Routing;

use Koansu\Core\Exceptions\ConfigurationException;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\InputHandler;
use Koansu\Routing\GenericInput;
use Koansu\Routing\Route;
use Koansu\DependencyInjection\Lambda;
use Koansu\Core\Response as CoreResponse;
use Koansu\Http\HttpResponse;
use Koansu\Routing\RoutedInputHandler;
use Koansu\Tests\TestCase;
use Koansu\Testing\LoggingCallable;
use Mockery;

use function func_get_args;
use function implode;
use function is_callable;
use ReflectionException;

class RoutedInputHandlerTest_TestController
{
    public $add = '';

    public function index()
    {
        return 'index was called: ' . implode(',', func_get_args());
    }

    public function edit()
    {
        return 'edit was called: ' . implode(',', func_get_args());
    }

    public function store()
    {
        return 'update was called: ' . implode(',', func_get_args());
    }

    public function show()
    {
        return 'show was called:' . $this->add  . implode(',', func_get_args());
    }
}

class RoutedInputHandlerTest extends TestCase
{

    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(InputHandler::class, $this->make());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_input_not_routed()
    {
        $handler = $this->make();
        $this->expectException(ConfigurationException::class);
        $handler($this->input('home'));
    }

    /**
     * @test
     */
    public function it_throws_exception_if_input_handler_not_callable()
    {
        $handler = $this->make();
        /** @var Input|Mockery\MockInterface $input */
        $input = $this->mock(Input::class);
        $input->shouldReceive('isRouted')->andReturn(true);
        $input->shouldReceive('getHandler')->andReturn(null);
        $this->expectException(ConfigurationException::class);
        $handler($input);
    }

    /**
     * @test
     */
    public function it_calls_the_route_handler_and_creates_HttpResponse()
    {
        $handler = $this->make();
        $f = new LoggingCallable(function () {
            return 'bar';
        });
        $input = $this->routedInput('some-url', $f);

        $response = $handler($input);
        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertEquals('bar', $response->payload);
    }

    /**
     * @test
     */
    public function it_calls_the_route_handler_and_creates_CoreResponse()
    {
        $handler = $this->make();
        $f = new LoggingCallable(function () {
            return 'bar';
        });
        $input = $this->routedInput('some-url', $f);
        $input->setClientType(Input::CLIENT_CONSOLE);

        $response = $handler($input);
        $this->assertNotInstanceOf(HttpResponse::class, $response);
        $this->assertInstanceOf(CoreResponse::class, $response);
        $this->assertEquals('bar', $response->payload);
    }

    /**
     * @test
     */
    public function it_calls_the_route_handler_and_passes_response_if_is_already_Response()
    {
        $handler = $this->make();
        $awaited = new HttpResponse('hello');
        $f = new LoggingCallable(function () use ($awaited) {
            return $awaited;
        });
        $input = $this->routedInput('some-url', $f);
        $input->setClientType(Input::CLIENT_CONSOLE);

        $this->assertSame($awaited, $handler($input));
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function it_assigns_factory_if_lambda_and_none_assigned()
    {
        $factory = function ($class) {
            $instance = new $class;
            $instance->add = ' foo';
            return $instance;
        };

        $handlerString = RoutedInputHandlerTest_TestController::class.'->show';
        $handler = $this->make($factory);

        $f = Lambda::f($handlerString);

        $input = $this->routedInput('some-url', $handlerString, $f);
        $this->assertSame('show was called: foo', $handler($input)->payload);
    }

    protected function make(callable $factory=null) : RoutedInputHandler
    {
        return new RoutedInputHandler($factory);
    }

    /**
     * @param $url
     * @param string $method
     * @param string $clientType
     * @param string $scope
     *
     * @return GenericInput
     */
    protected function input($url, string $method=Input::GET, string $clientType=Input::CLIENT_WEB, string $scope='default')
    {
        $routable = new GenericInput();
        if (!$url instanceof Url) {
            $url = new Url($url);
        }
        return $routable->setMethod($method)->setUrl($url)->setClientType($clientType)->setRouteScope($scope);
    }

    /**
     * @param $url
     * @param mixed $handler
     * @return Input
     */
    protected function routedInput($url, $handler, callable $realHandler=null)
    {
        $uri = $url instanceof Url ? (string)$url->path : $url;
        $input = $this->input($url);
        $route = new Route($input->getMethod(), $uri, $handler);
        $handler = is_callable($handler) ? $handler : function () {};
        return $input->makeRouted($route, $realHandler ?: $handler);
    }
}
