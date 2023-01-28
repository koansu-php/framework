<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:41.
 **/

namespace Koansu\Tests\Routing\FastRoute;

use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Routing\Exceptions\MethodNotAllowedException;
use Koansu\Routing\Exceptions\RouteNotFoundException;
use Koansu\Routing\FastRoute\FastRouteDispatcher;
use Koansu\Routing\RouteHit;
use Koansu\Routing\CurlyBraceRouteCompiler;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;
use Koansu\Testing\Cheat;
use FastRoute\DataGenerator;
use FastRoute\Dispatcher as FastRouteDispatcherContract;

class FastRouteDispatcherTest extends TestCase
{
    use TestData;

    protected static $testRoutes = [];

    /**
     * This method is called before the first test of this test class is run.
     * @beforeClass
     */
    public static function loadTestRoutes()
    {
        static::$testRoutes = static::includeDataFile('routing/basic-routes.php');
    }


    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(FastRouteDispatcher::class, $this->make());
    }

    /**
     * @test
     */
    public function add_adds_route()
    {
        $dispatcher = $this->make();
        $method = 'GET';
        $pattern = 'users';
        $handler = 'UserController@index';

        $dispatcher->add($method, $pattern, $handler);
        $hit = $dispatcher->match($method, $pattern);
        $this->assertInstanceOf(RouteHit::class, $hit);
        $this->assertEquals($method, $hit->method);
        $this->assertEquals($pattern, $hit->pattern);
        $this->assertEquals($handler, $hit->handler);
        $this->assertEquals([], $hit->parameters);

    }

    /**
     * @test
     */
    public function match_throws_exception_if_route_did_not_match()
    {
        $dispatcher = $this->make();
        $this->expectException(RouteNotFoundException::class);
        $dispatcher->match('GET', 'cars');
    }

    /**
     * @test
     */
    public function match_throws_exception_if_method_did_not_match()
    {
        $dispatcher = $this->make();
        $dispatcher->add('GET', 'cars', 'CarController@index');
        $dispatcher->add('POST', 'cars', 'CarController@store');
        $this->expectException(MethodNotAllowedException::class);
        $dispatcher->match('PUT', 'cars');
    }

    /**
     * @test
     */
    public function match_throws_exception_if_handler_not_wellformed()
    {
        $dispatcher = $this->make();
        $fDispatcher = $this->mock(FastRouteDispatcherContract::class);

        Cheat::set($dispatcher, 'dispatcher', $fDispatcher);
        $method = 'GET';
        $uri = 'orders';

        $fDispatcher->shouldReceive('dispatch')
            ->with($method, $uri)
            ->andReturn([
                            0 => FastRouteDispatcherContract::FOUND,
                            1 => 'Not what he expect'
                        ]);
        $this->expectException(DataIntegrityException::class);
        $dispatcher->match($method, $uri);
    }

    /**
     * @test
     */
    public function match_various_routes()
    {
        $dispatcher = $this->make();

        foreach (static::$testRoutes as $test) {
            $dispatcher->add($test['method'], $test['pattern'], $test['handler']);
        }

        foreach (static::$testRoutes as $test) {
            $hit = $dispatcher->match($test['method'], $test['uri']);
            $this->assertInstanceOf(RouteHit::class, $hit);
            $this->assertEquals($test['method'], $hit->method);
            $this->assertEquals($test['pattern'], $hit->pattern);
            $this->assertEquals($test['handler'], $hit->handler);
            $this->assertEquals($test['parameters'], $hit->parameters);
            $this->assertEquals($test['uri'], $dispatcher->path($test['pattern'], $test['parameters']));
        }
    }

    /**
     * @test
     */
    public function store_and_restore_state_by_toArray_and_fill()
    {
        $dispatcherForRegister = $this->make();

        foreach (static::$testRoutes as $test) {
            $dispatcherForRegister->add($test['method'], $test['pattern'], $test['handler']);
        }

        $dispatcher = $this->make();

        $dispatcher->fill($dispatcherForRegister->__toArray());

        foreach (static::$testRoutes as $test) {
            $hit = $dispatcher->match($test['method'], $test['uri']);
            $this->assertInstanceOf(RouteHit::class, $hit);
            $this->assertEquals($test['method'], $hit->method);
            $this->assertEquals($test['pattern'], $hit->pattern);
            $this->assertEquals($test['handler'], $hit->handler);
            $this->assertEquals($test['parameters'], $hit->parameters);
            $this->assertEquals($test['uri'], $dispatcher->path($test['pattern'], $test['parameters']));
        }

    }

    /**
     * @test
     */
    public function CharCountBasedDispatcher()
    {

        $dispatcher = $this->make(new DataGenerator\CharCountBased());
        $method = 'GET';
        $pattern = 'users';
        $handler = 'UserController@index';

        $dispatcher->add($method, $pattern, $handler);
        $hit = $dispatcher->match($method, $pattern);
        $this->assertInstanceOf(RouteHit::class, $hit);
        $this->assertEquals($method, $hit->method);
        $this->assertEquals($pattern, $hit->pattern);
        $this->assertEquals($handler, $hit->handler);
        $this->assertEquals([], $hit->parameters);

    }

    /**
     * @test
     */
    public function GroupPosBasedDispatcher()
    {

        $dispatcher = $this->make(new DataGenerator\GroupPosBased());
        $method = 'GET';
        $pattern = 'users';
        $handler = 'UserController@index';

        $dispatcher->add($method, $pattern, $handler);
        $hit = $dispatcher->match($method, $pattern);
        $this->assertInstanceOf(RouteHit::class, $hit);
        $this->assertEquals($method, $hit->method);
        $this->assertEquals($pattern, $hit->pattern);
        $this->assertEquals($handler, $hit->handler);
        $this->assertEquals([], $hit->parameters);

    }

    /**
     * @test
     */
    public function MarkBasedDispatcher()
    {

        $dispatcher = $this->make(new DataGenerator\MarkBased());
        $method = 'GET';
        $pattern = 'users';
        $handler = 'UserController@index';

        $dispatcher->add($method, $pattern, $handler);
        $hit = $dispatcher->match($method, $pattern);
        $this->assertInstanceOf(RouteHit::class, $hit);
        $this->assertEquals($method, $hit->method);
        $this->assertEquals($pattern, $hit->pattern);
        $this->assertEquals($handler, $hit->handler);
        $this->assertEquals([], $hit->parameters);

    }

    /**
     * @test
     */
    public function unknown_DataGenerator_throws_exception()
    {

        /** @var DataGenerator $dataGenerator */
        $dataGenerator = $this->mock(DataGenerator::class);
        $dataGenerator->shouldReceive('addRoute');
        $dataGenerator->shouldReceive('getData')->andReturn([]);

        $dispatcher = $this->make($dataGenerator);
        $method = 'GET';
        $pattern = 'users';
        $handler = 'UserController@index';

        $dispatcher->add($method, $pattern, $handler);
        $this->expectException(ImplementationException::class);
        $hit = $dispatcher->match($method, $pattern);
        $this->assertInstanceOf(RouteHit::class, $hit);
        $this->assertEquals($method, $hit->method);
        $this->assertEquals($pattern, $hit->pattern);
        $this->assertEquals($handler, $hit->handler);
        $this->assertEquals([], $hit->parameters);

    }

    protected function make(DataGenerator $dataGenerator=null, CurlyBraceRouteCompiler $compiler=null) : FastRouteDispatcher
    {
        return new FastRouteDispatcher($dataGenerator, $compiler);
    }
}