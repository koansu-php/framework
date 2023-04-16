<?php
/**
 *  * Created by mtils on 03.12.2022 at 13:58.
 **/

namespace Koansu\Tests\Skeleton;

use Koansu\Core\Response;
use Koansu\Http\Cookie;
use Koansu\Http\HttpResponse;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Skeleton\Contracts\IOAdapter;
use Koansu\Skeleton\WebserverIOAdapter;
use Koansu\Tests\TestCase;

use Psr\Http\Message\UriInterface;

use function ob_end_clean;
use function ob_get_contents;
use function ob_start;
use function strpos;
use function substr;

class WebserverIOAdapterTest extends TestCase
{

    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(IOAdapter::class, $this->make());
    }

    /**
     * @test
     */
    public function open_opens_and_closes_connection()
    {
        $con = $this->make();
        $this->assertFalse($con->isOpen());
        $con->open();
        $this->assertTrue($con->isOpen());
        $con->close();

    }

    /**
     * @test
     */
    public function write_string()
    {
        $con = $this->make([], [], ['host' => 'example.com']);
        ob_start();
        $test = 'Hello';
        $con(function (Input $input, callable $output) use ($test) {
            return $output($test);
        });
        $string = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($test, $string);
    }

    /**
     * @test
     */
    public function write_http_response()
    {
        $con = $this->make([], [], ['host' => 'example.com']);
        ob_start();

        $headers = [];

        $headerPrinter = function ($header, $replace=true) use (&$headers) {
            $headers[] = $header;
        };
        $con->outputHeaderBy($headerPrinter);

        $response = new HttpResponse('Hello', [
            'foo: bar'
        ]);

        $con->fakeSentHeaders(false);

        $con(function (Input $input, callable $output) use ($response) {
            return $output($response);
        });

        $string = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('Hello', $string);
        $found = false;
        foreach ($headers as $header) {
            if ($header == 'foo: bar') {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Added header was in response');
    }

    /**
     * @test
     */
    public function write_http_response_with_sent_headers()
    {
        $con = $this->make([], [], ['host' => 'example.com']);
        ob_start();

        $headers = [];

        $headerPrinter = function ($name, $replace=true) use (&$headers) {
            $headers[] = $name;
        };
        $con->outputHeaderBy($headerPrinter);

        $response = new HttpResponse('Hello', [
            'foo: bar'
        ]);

        $con->fakeSentHeaders(true);

        $con(function (Input $input, callable $output) use ($response) {
            return $output($response);
        });

        $string = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('Hello', $string);
        $this->assertCount(0, $headers);
    }

    /**
     * @test
     */
    public function write_status_line_without_phrase()
    {
        $headers = [
            'Host'              => 'example.com',
            'Content-Type'      => 'application/json',
            'Content-Encoding'  => 'gzip'
        ];
        $response = new HttpResponse('Nothing is here', $headers, 404);

        list($headerLines, $body) = $this->render($response);

        $this->assertStringStartsWith('HTTP/', $headerLines[0]);
        $this->assertStringContainsString($response->protocolVersion, $headerLines[0]);
        $this->assertStringContainsString((string)$response->status, $headerLines[0]);
        $this->assertStringNotContainsString('Found', $headerLines[0]);

        $this->assertEquals("$response", $body);
    }

    /**
     * @test
     */
    public function write_status_line_with_phrase()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip'
        ];
        $response = new HttpResponse('Nothing is here', $headers);
        $response = $response->withStatus(404, 'Nothing found here');

        list($headerLines, $body) = $this->render($response);

        $this->assertStringStartsWith('HTTP/', $headerLines[0]);
        $this->assertStringContainsString($response->protocolVersion, $headerLines[0]);
        $this->assertStringContainsString((string)$response->status, $headerLines[0]);
        $this->assertStringContainsString($response->getReasonPhrase(), $headerLines[0]);
        $this->assertEquals("$response", $body);
    }

    /**
     * @test
     */
    public function write_cookie_into_headers()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip'
        ];
        $response = new HttpResponse('Success', $headers);
        $response = $response->withCookie('foo', 'bar')
            ->withCookie('test', 'content', 90, '/users','localhost', true, true, Cookie::STRICT);

        list($headerLines, $body) = $this->render($response);

        $cookieHeaders = $this->cookieHeaders($headerLines);

        $this->assertStringContainsString('bar', $cookieHeaders['foo']);
        $this->assertStringContainsString('content', $cookieHeaders['test']);
        $this->assertStringContainsString('expires', $cookieHeaders['test']);
        $this->assertStringContainsString('Max-Age', $cookieHeaders['test']);
        $this->assertStringContainsString('/users', $cookieHeaders['test']);
        $this->assertStringContainsString('localhost', $cookieHeaders['test']);
        $this->assertStringContainsString('secure', $cookieHeaders['test']);
        $this->assertStringContainsString('httponly', $cookieHeaders['test']);
        $this->assertStringContainsString(Cookie::STRICT, $cookieHeaders['test']);


        $this->assertEquals("$response", $body);
    }

    /**
     * @test
     */
    public function write_cookies_into_headers()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip'
        ];
        $response = new HttpResponse('Success', $headers);
        $response = $response->withCookie('foo', 'bar');

        list($headerLines, $body) = $this->render($response);

        $cookieHeaders = $this->cookieHeaders($headerLines);

        $this->assertStringContainsString('bar', $cookieHeaders['foo']);
        $this->assertEquals("$response", $body);
    }

    /**
     * @test
     */
    public function isInteractive_returns_false()
    {
        $this->assertFalse($this->make()->isInteractive());
    }

    /**
     * @test
     */
    public function read_reads_input_into_handler()
    {
        $request = ['foo' => 'bar'];
        $con = $this->make($request, [
            'SERVER_PORT'   => 443,
            'REQUEST_URI'   => 'test',
            'REQUEST_METHOD'=> 'GET'
        ], ['Host' => 'web-utils.de']);

        $inputs = [];

        $handler = function (Input $input) use (&$inputs) {
            $inputs[] = $input;
        };

        $con->read($handler);
        /** @var HttpInput $input */
        $input = $inputs[0];
        $this->assertEquals([], $input->custom);
        $this->assertEquals($request, $input->__toArray());
        $this->assertEquals('https://web-utils.de/test', (string)$input->uri);
    }

    /**
     * @test
     */
    public function createServerRequest_is_valid()
    {
        $request = ['foo' => 'bar'];
        $server = [
            'SERVER_PORT'   => 443,
            'DOCUMENT_ROOT' => '/dev/null'
        ];
        $url = 'https://web-utils.de/test';
        $con = $this->make($request);

        $request = $con->createServerRequest('POST', $url, $server);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals($url, (string)$request->getUri());
        $this->assertInstanceOf(UriInterface::class, $request->getUri());
        $this->assertEquals($server, $request->getServerParams());

    }

    protected function render(Response $response)
    {
        $con = $this->make([], [], ['host' => 'example.com']);
        ob_start();

        $headers = [];
        $headerPrinter = function ($name, $replace=true) use (&$headers) {
            $headers[] = $name;
        };

        $con->outputHeaderBy($headerPrinter);
        $con->fakeSentHeaders(false);

        $con(function (Input $input, callable $output) use ($response) {
            return $output($response);
        });
        $string = ob_get_contents();
        ob_end_clean();
        return [$headers, $string];
    }

    protected function cookieHeaders(array $headers) : array
    {
        $cookieHeaders = [];
        foreach ($headers as $line) {
            if (strpos($line,'Set-Cookie:') !== 0) {
                continue;
            }
            $cookieLine = trim(substr($line, 11));
            $parts = explode('=', $cookieLine, 2);
            $cookieHeaders[$parts[0]] = $cookieLine;
        }
        return $cookieHeaders;
    }

    protected function make(array $query=[], array $server=[], array $headers=[]) : WebserverIOAdapter
    {
        return new WebserverIOAdapter($query, $server, $headers);
    }
}