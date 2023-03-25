<?php
/**
 *  * Created by mtils on 05.03.2023 at 20:52.
 **/

namespace Koansu\Tests\Routing;

use Koansu\Core\Serializer;
use Koansu\Http\HttpResponse;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Routing\Middleware\SessionGuard;
use Koansu\Routing\Session;
use Koansu\Routing\SessionHandler\ArraySessionHandler;
use Koansu\Tests\TestCase;
use SessionHandlerInterface;

use function serialize;

class SessionGuardTest extends TestCase
{
    /**
     * @test
     */
    public function it_instantiates()
    {
        $this->assertInstanceOf(SessionGuard::class, $this->guard());
    }

    /**
     * @test
     **/
    public function it_creates_the_session()
    {
        $guard = $this->guard();
        $input = new HttpInput();
        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $nextInput = $input;
            return new HttpResponse();
        };

        $guard($input, $next);
        $this->assertInstanceOf(Session::class, $nextInput->session);
    }

    /**
     * @test
     **/
    public function if_reads_session_id_from_cookie()
    {
        $guard = $this->guard();
        $id = 'abcdefghijklmnopqrstuvw';
        $input = (new HttpInput())->withCookieParams([$guard->getCookieName() => $id]);

        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $nextInput = $input;
            return new HttpResponse();
        };

        $guard($input, $next);
        $this->assertEquals($id, $nextInput->session->getId());
    }

    /**
     * @test
     **/
    public function if_does_not_create_a_cookie_if_already_exists()
    {
        $guard = $this->guard();
        $id = 'abcdefghijklmnopqrstuvw';
        $input = (new HttpInput())->withCookieParams([$guard->getCookieName() => $id]);

        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $nextInput = $input;
            return new HttpResponse();
        };

        /** @var HttpResponse $response */
        $response = $guard($input, $next);
        $this->assertEmpty($response->cookies);

    }

    /**
     * @test
     **/
    public function if_reads_session_data()
    {

        $id = 'abcdefghijklmnopqrstuvw';

        $handler = $this->handler();
        $serializer = $this->serializer();
        $guard = $this->guard($handler, $serializer);

        $input = (new HttpInput())->withCookieParams([$guard->getCookieName() => $id]);

        $sessionData = [
            'foo'   => 'bar',
            'user'  => 3
        ];

        $handler->write($id, $serializer->serialize($sessionData));
        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $nextInput = $input;
            return new HttpResponse();
        };

        $guard($input, $next);
        $this->assertEquals($id, $nextInput->session->getId());
        $this->assertEquals($sessionData, $nextInput->session->__toArray());
    }

    /**
     * @test
     **/
    public function it_destroys_session_if_session_empty()
    {
        $handler = $this->mock(SessionHandlerInterface::class);
        $guard = $this->guard($handler);
        $id = 'abcdefghijklmnopqrstuvw';
        $handler->shouldReceive('read')->with($id)
            ->andReturn(serialize([]));

        $input = (new HttpInput())->withCookieParams([$guard->getCookieName() => $id]);

        $handler->shouldReceive('destroy')->with($id)->once();

        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $nextInput = $input;
            return new HttpResponse();
        };

        $guard($input, $next);
    }

    /**
     * @test
     **/
    public function it_does_not_destroy_session_if_session_not_empty()
    {
        $handler = $this->mock(SessionHandlerInterface::class);
        $guard = $this->guard($handler);
        $id = 'abcdefghijklmnopqrstuvw';
        $handler->shouldReceive('read')->with($id)
            ->andReturn(serialize(['foo' => 'bar']));

        $input = (new HttpInput())->withCookieParams([$guard->getCookieName() => $id]);

        $handler->shouldReceive('destroy')->never();

        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $nextInput = $input;
            return new HttpResponse();
        };

        $guard($input, $next);
    }

    /**
     * @test
     */
    public function it_creates_session_cookie_if_it_did_not_exist()
    {
        $id = 'abcdefghijklmnopqrstuvw';

        $handler = $this->handler();
        $serializer = $this->serializer();
        $guard = $this->guard($handler, $serializer);

        $input = new HttpInput();

        $sessionData = [
            'foo'   => 'bar',
            'user'  => 3
        ];

        $handler->write($id, $serializer->serialize($sessionData));
        $nextInput = null;
        $next = function (Input $input) use (&$nextInput) {
            $input->session['foo'] = 'bar';
            $nextInput = $input;
            return new HttpResponse();
        };

        /** @var HttpResponse $response */
        $response = $guard($input, $next);
        $name = $guard->getCookieName();
        $this->assertEquals($response->cookies[$name]->value, $nextInput->session->getId());
    }

    /**
     * @test
     */
    public function it_creates_session_cookie_if_session_id_was_changed()
    {
        $id = 'abcdefghijklmnopqrstuvw';
        $id2 = 'wvutsrqponmlkjihgfedcba';

        $handler = $this->handler();
        $serializer = $this->serializer();
        $guard = $this->guard($handler, $serializer);

        $input = (new HttpInput())->withCookieParams([$guard->getCookieName() => $id]);

        $sessionData = [
            'foo'   => 'bar',
            'user'  => 3
        ];

        $handler->write($id, $serializer->serialize($sessionData));
        $nextInput = null;
        $next = function (Input $input) use (&$nextInput, $id2) {
            $input->session['foo'] = 'bar';
            $input->session->setId($id2);
            $nextInput = $input;
            return new HttpResponse();
        };

        /** @var HttpResponse $response */
        $response = $guard($input, $next);
        $name = $guard->getCookieName();
        $this->assertEquals($response->cookies[$name]->value, $id2);
    }

    protected function guard(SessionHandlerInterface $handler=null, Serializer $serializer=null) : SessionGuard
    {
        return new SessionGuard($handler ?: $this->handler(), $serializer ?:$this->serializer());
    }

    protected function handler(array &$array=[]) : ArraySessionHandler
    {
        return new ArraySessionHandler($array);
    }

    protected function serializer() : Serializer
    {
        return new Serializer();
    }
}