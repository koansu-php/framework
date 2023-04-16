<?php
/**
 *  * Created by mtils on 26.10.2022 at 08:23.
 **/

namespace Koansu\Tests\Routing;

use Koansu\Core\Message;
use Koansu\Core\Url;
use Koansu\Routing\ConsoleInput;
use Koansu\Routing\Command;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Route;
use Koansu\Tests\TestCase;

use function get_class;

class ConsoleInputTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(ConsoleInput::class, $this->make());
    }

    /**
     * @test
     */
    public function it_assigns_arguments_as_query_parameters_on_get()
    {
        $tenant = '101';
        $input = $this->make(['console', $tenant]);

        $command = (new Command('users:index'))->argument('tenant');
        $route = new Route('GET', 'users', '');
        $route->command($command);
        $input = $input->makeRouted($route, function () {});

        $this->assertEquals($tenant, $input->get('tenant'));

    }

    /**
     * @test
     */
    public function it_assigns_arguments_as_query_parameters_on_getOfFail()
    {
        $tenant = '101';
        $input = $this->make(['console', $tenant]);

        $command = (new Command('users:index'))->argument('tenant');
        $route = new Route('GET', 'users', '');
        $route->command($command);
        $input = $input->makeRouted($route, function () {});

        $this->assertEquals($tenant, $input->getOrFail('tenant'));

    }

    /**
     * @test
     */
    public function it_assigns_arguments_as_query_parameters_on_offsetExists()
    {
        $tenant = '101';
        $input = $this->make(['console', $tenant]);

        $command = (new Command('users:index'))->argument('tenant');
        $route = new Route('GET', 'users', '');
        $route->command($command);
        $input = $input->makeRouted($route, function () {});

        $this->assertTrue($input->offsetExists('tenant'));
        $this->assertFalse($input->offsetExists('foo'));

    }

    /**
     * @test
     */
    public function it_assigns_arguments_as_query_parameters_on_offsetGet()
    {
        $tenant = '101';
        $input = $this->make(['console', $tenant]);

        $command = (new Command('users:index'))->argument('tenant');
        $route = new Route('GET', 'users', '');
        $route->command($command);
        $input = $input->makeRouted($route, function () {});

        $this->assertEquals($tenant, $input['tenant']);

    }

    /**
     * @test
     */
    public function it_assigns_arguments_as_query_parameters_on_toArray()
    {
        $tenant = '101';
        $input = $this->make(['console', $tenant]);

        $command = (new Command('users:index'))->argument('tenant');
        $route = new Route('GET', 'users', '');
        $route->command($command);
        $input = $input->makeRouted($route, function () {});

        $inputData = $input->__toArray();
        $this->assertEquals($tenant, $inputData['tenant']);
    }

    /**
     * @test
     */
    public function it_assigns_options_as_query_parameters_on_get()
    {
        $tenant = '101';
        $force = '';
        $input = $this->make(['console', $tenant, '--force']);

        $command = (new Command('users:index'))->argument('tenant')
            ->option('force');
        $route = new Route('GET', 'users', '');
        $route->command($command);
        $input = $input->makeRouted($route, function () {});

        $this->assertTrue($input->get('force'));
        $this->assertEquals($tenant, $input->get('tenant'));

    }

    /**
     * @test
     */
    public function it_is_routable()
    {
        $argv = ['console', 'users:show', '--parameters=3', '--long'];
        $input = $this->make($argv);

        $route = new Route('GET', 'users', '');
        $handler = function () {};
        $parameters = [3];

        $routed = $input->makeRouted($route, $handler, $parameters);
        $this->assertNotSame($routed, $input);
        $this->assertSame(get_class($routed), get_class($input));

        $this->assertSame($route, $routed->getMatchedRoute());
        $this->assertSame($handler, $routed->getHandler());
        $this->assertSame($parameters, $routed->getRouteParameters());

        $this->assertSame($argv, $input->getArgv());
        $this->assertSame($argv, $routed->getArgv());

    }

    /**
     * @test
     */
    public function property_access()
    {
        // [X] argv
        // [x] arguments
        // [x] options
        // [X] matchedRoute
        // [X] handler
        // [X] routeParameters
        // [x] url
        // [x] method
        // [x] clientType
        // [X] routeScope
        // [X] locale
        // [x] determinedContentType
        // [X] apiVersion

        $args = [
            'argv' => ['console', '12', '--force'],
            'routeScope' => 'tenant-12',
            'locale'     => 'de_CH',
            'apiVersion' => '1.1'
        ];

        $input = $this->make($args['argv'])
            ->withLocale($args['locale'])
            ->withApiVersion($args['apiVersion'])
            ->withRouteScope($args['routeScope']);

        $this->assertEquals($args['argv'], $input->argv);
        $this->assertEquals($args['routeScope'], $input->routeScope);
        $this->assertEquals($args['locale'], $input->locale);
        $this->assertEquals($args['apiVersion'], $input->apiVersion);

        $this->assertNull($input->matchedRoute);
        $this->assertNull($input->handler);
        $this->assertSame([], $input->routeParameters);
        $this->assertInstanceOf(Url::class, $input->url);
        $this->assertEquals('console:' . $args['argv'][1], "$input->url");
        $this->assertEquals(Input::CONSOLE, $input->method);
        $this->assertEquals(Input::CLIENT_CONSOLE, $input->clientType);
        $this->assertEquals('text/x-ansi', $input->determinedContentType);

        $command = (new Command('users:index'))->argument('tenant')
            ->option('force');
        $route = new Route('GET', 'users', '');
        $route->command($command);

        /** @var ConsoleInput $routed */
        $handler = function () {};
        $routeParameters = [3];
        $routed = $input->makeRouted($route, $handler, $routeParameters);

        $this->assertSame($route, $routed->matchedRoute);
        $this->assertSame($routeParameters, $routed->routeParameters);
        $this->assertSame($handler, $routed->handler);
        $this->assertSame($input->url, $routed->url);

        $this->assertEquals($args['argv'], $routed->argv);
        $this->assertEquals($args['routeScope'], $routed->routeScope);
        $this->assertEquals($args['locale'], $routed->locale);
        $this->assertEquals($args['apiVersion'], $routed->apiVersion);

        $this->assertEquals(Input::CONSOLE, $routed->method);
        $this->assertEquals(Input::CLIENT_CONSOLE, $routed->clientType);

        $this->assertEquals($args['argv'][1], $routed->arguments['tenant']);
        $this->assertTrue($routed->options['force']);

        $fork = $routed->withRouteScope('tenant-44');
        $this->assertNotSame($fork, $routed);
        $this->assertSame($route, $fork->getMatchedRoute());
        $this->assertEquals('tenant-44', $fork->getRouteScope());

    }

    /**
     * @test
     **/
    public function test_getFrom()
    {
        $argv = ['console', 'users:show', '--parameters=3', '--long'];
        $custom = ['foo' => 'bar'];

        $route = new Route('GET', 'users', '');
        $command = (new Command('users:index'))
            ->argument('route')
            ->option('parameters=')
            ->option('long');

        $route->command($command);

        $handler = function () {};
        $parameters = [3];

        $input = $this->make($argv)->with($custom);

        $input = $input->makeRouted($route, $handler, $parameters);

        $this->assertEquals('3', $input->getFrom(Message::POOL_ARGV, 'parameters'));
        $this->assertTrue($input->getFrom(Message::POOL_ARGV, 'long'));
        $this->assertEquals('users:show', $input->getFrom(Message::POOL_ARGV, 'route'));
    }


    /**
     * @param array $argv
     * @return ConsoleInput
     */
    public function make(array $argv=[]) : ConsoleInput
    {
        return new ConsoleInput($argv);
    }
}