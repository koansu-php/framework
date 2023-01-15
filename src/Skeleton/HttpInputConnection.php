<?php
/**
 *  * Created by mtils on 03.12.2022 at 08:28.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\AbstractConnection;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Skeleton\Contracts\InputConnection;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function getallheaders;

class HttpInputConnection extends AbstractConnection implements InputConnection, ServerRequestFactoryInterface
{
    /**
     * @var array
     */
    protected $query;

    /**
     * @var array
     */
    protected $server;
    /**
     * @var array
     */
    private $headers;
    /**
     * @var array
     */
    private $body;
    /**
     * @var array
     */
    private $cookies;
    /**
     * @var array
     */
    private $files;

    public function __construct(array $query=[], array $server=[], array $headers=[], array $body=[], array $cookies=[], array $files=[])
    {
        parent::__construct();
        $this->query = $query ?: $_GET;
        $this->server = $server ?: $_SERVER;
        $this->headers = $headers;
        $this->body = $body ?: $_POST;
        $this->cookies = $cookies ?: $_COOKIE;
        $this->files = $files ?: $_FILES;
        parent::__construct('php://stdin');
    }

    /**
     * {@inheritDoc}
     *
     * @param ?callable $into
     *
     * @return Input
     */
    public function read(callable $into=null) : Input
    {
        $input = $this->createInput();
        if ($into) {
            $into($input);
        }
        return $input;
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function isInteractive() : bool
    {
        return false;
    }

    /**
     * @param string $method
     * @param $uri
     * @param array $serverParams
     * @return ServerRequestInterface
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return $this->createInput($serverParams, $method, $uri instanceof Url ? $uri : new Url((string)$uri));
    }

    /**
     * @param Url $url
     *
     * @return resource
     */
    protected function createResource(Url $url)
    {
        return fopen($this->uri, 'r');
    }

    protected function createInput(array $serverParams=[], string $method='', Url $uri=null) : HttpInput
    {
        $server = $serverParams ?: $this->server;

        $attributes = [
            Input::FROM_QUERY       => $this->query,
            Input::FROM_BODY        => $this->body,
            Input::FROM_COOKIE      => $this->cookies,
            Input::FROM_SERVER      => $server,
            Input::FROM_FILES       => $this->files,
            'uri'                   => $uri ?: $this->createUrl($server),
            'method'                => $method ?: $server['REQUEST_METHOD'],
            'headers'               => $this->headers ?: $this->guessHeaders(),
            'determinedContentType' => 'text/html'
        ];
        $attributes = [
            'uri'                   => $uri ?: $this->createUrl($server),
            'method'                => $method ?: $server['REQUEST_METHOD'],
            'determinedContentType' => 'text/html'
        ];
        return new HttpInput(
            $attributes,
            $this->headers ?: $this->guessHeaders(),
            $this->query,
            $this->body,
            $this->cookies,
            $this->files,
            $server
        );
    }

    /**
     * @param array $server
     * @return Url
     */
    protected function createUrl(array $server) : Url
    {
        /** @noinspection HttpUrlsUsage */
        $protocol = ((!empty($server['HTTPS']) && $server['HTTPS'] != 'off') || $server['SERVER_PORT'] == 443) ? "https://" : "http://";

        $path = isset($server['REQUEST_URI']) ? ('/' . ltrim($server['REQUEST_URI'],'/')) : '';
        $url = $protocol . $server['HTTP_HOST'] . $path;

        return new Url($url);
    }

    /**
     * @return array
     */
    protected function guessHeaders() : array
    {
        return getallheaders() ?: [];
    }
}