<?php
/**
 *  * Created by mtils on 03.12.2022 at 09:41.
 **/

namespace Koansu\Tests\Skeleton;

use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Skeleton\Contracts\InputConnection;
use Koansu\Skeleton\HttpInputConnection;
use Koansu\Tests\TestCase;
use Psr\Http\Message\UriInterface;

class HttpInputConnectionTest extends TestCase
{
    /**
     * @test
     **/
    public function it_implements_interface()
    {
        $this->assertInstanceOf(InputConnection::class, $this->make());
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
            'HTTP_HOST'     => 'web-utils.de',
            'REQUEST_URI'   => 'test',
            'REQUEST_METHOD'=> 'GET'
        ]);

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

    /**
     * @test
     */
    public function open_and_close()
    {
        $con = $this->make();
        $this->assertFalse($con->isOpen());
        $con->open();
        $this->assertTrue($con->isOpen());
        $con->close();
    }

    protected function make($query=[], $server=[]) : HttpInputConnection
    {
        return new HttpInputConnection($query, $server);
    }
}
