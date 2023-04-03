<?php
/**
 *  * Created by mtils on 24.10.2022 at 20:07.
 **/

namespace Koansu\Tests\Http;

use Koansu\Tests\TestCase;
use Koansu\Core\Message;
use Koansu\Core\Stream;
use Koansu\Routing\Contracts\Input;
use Koansu\Core\ImmutableMessage;
use Koansu\Core\Url;
use Koansu\Http\HttpRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

class HttpRequestTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $request = $this->request();
        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertInstanceOf(ImmutableMessage::class, $request);
    }

    /**
     * @test
     */
    public function it_applies_all_attributes()
    {
        $url = 'https://web-utils.de/users/12/blog-entries';
        $attributes = [
            'custom'    => ['foo' => 'bar'],
            'headers'   => ['Content-Type' =>  'application/json'],
            'payload'   => 'raw_body_content',
            'protocolVersion' => '2.0',
            'requestTarget' => '/users/12/blog-entries',
            'method'        => 'PUT',
            'uri'           => new Url($url)
        ];

        $request = $this->request($attributes['method'], $attributes['uri'], $attributes['headers'], $attributes['payload'])
            ->with($attributes['custom'])
            ->withProtocolVersion($attributes['protocolVersion'])
            ->withRequestTarget($attributes['requestTarget']);

        foreach ($attributes as $key=>$value) {
            $this->assertEquals($value, $request->$key);
        }
    }

    /**
     * @test
     */
    public function it_applies_separate_constructor_attributes()
    {
        $data = ['foo' => 'bar'];
        $headers = ['Content-Type' =>  'application/json'];

        $request = $this->request('GET', null, $headers, $data);
        $this->assertEquals($data, $request->payload);
        $this->assertEquals($data, $request->custom);
        $this->assertEquals($headers, $request->headers);
    }

    /**
     * @test
     */
    public function it_creates_body()
    {
        $request = $this->request('GET', null, [], 'blob');

        $this->assertEquals('blob', $request->payload);
        $body = $request->body;
        $this->assertInstanceOf(Stream::class, $body);
        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertEquals('blob', "$body");
    }

    /**
     * @test
     */
    public function getRequestTarget_returns_target()
    {
        $request = $this->request('blob');
        $this->assertSame('/', $request->getRequestTarget());

        $request = $this->request('GET', new Url('https://web-utils.de/api/users/12'));
        $this->assertSame('/api/users/12', $request->getRequestTarget());
        $fork = $request->withRequestTarget('api/v2/contacts/33');
        $this->assertNotSame($request, $fork);
        $this->assertEquals('api/v2/contacts/33',$fork->getRequestTarget());
        $this->assertEquals('api/v2/contacts/33',$fork->requestTarget);
    }

    /**
     * @test
     */
    public function getMethod_returns_method()
    {
        $method = Input::GET;
        $request = $this->request($method, null, [], 'blob');
        $this->assertSame($method, $request->method);

        $request = $this->request(Input::PUT);
        $this->assertSame(Input::PUT, $request->method);
        $fork = $request->withMethod(Input::POST);
        $this->assertNotSame($request, $fork);
        $this->assertEquals(Input::POST, $fork->getMethod());
    }

    /**
     * @test
     */
    public function getUri_returns_uri()
    {
        $url = new Url('https://web-utils.de/api/users/12');
        $request = $this->request('GET', $url);
        $this->assertSame($url, $request->getUri());
        $this->assertSame($url, $request->uri);
        $this->assertSame($url, $request->url);

        $url2 = new Url('https://web-utils.de/api/users/12/projects/2');
        $fork = $request->withUri($url2);
        $this->assertNotSame($request, $fork);
        $this->assertSame($url2, $fork->uri);
    }

    protected function request(...$args) : HttpRequest
    {
        return new HttpRequest(...$args);
    }
}