<?php
/**
 *  * Created by mtils on 14.04.2023 at 09:05.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\AbstractConnection;
use Koansu\Core\Response;
use Koansu\Core\Url;
use Koansu\Http\Cookie;
use Koansu\Http\CookieSerializer;
use Koansu\Http\HttpParser;
use Koansu\Http\HttpResponse;
use Koansu\Routing\HttpInput;
use Koansu\Skeleton\Contracts\IOAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function array_map;
use function call_user_func;
use function file_get_contents;
use function fopen;
use function fwrite;
use function headers_sent;
use function in_array;
use function ini_get;
use function is_bool;
use function is_object;
use function method_exists;
use function strtolower;

class WebserverIOAdapter extends AbstractConnection implements IOAdapter, ServerRequestFactoryInterface
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

    /**
     * @var HttpParser
     */
    private $parser;

    /**
     * @var callable
     */
    private $headerPrinter;

    /**
     * @var bool|null
     */
    protected $fakeSentHeaders;

    /**
     * @var callable
     */
    protected $cookieSerializer;

    public function __construct(array $query=[], array $server=[], array $headers=[], array $body=[], array $cookies=[], array $files=[])
    {
        $this->query = $query ?: $_GET;
        $this->server = $server ?: $_SERVER;
        $this->headers = $headers;
        $this->body = $body ?: $_POST;
        $this->cookies = $cookies ?: $_COOKIE;
        $this->files = $files ?: $_FILES;
        $this->parser = new HttpParser();
        parent::__construct('php://stdin');
    }

    public function read(callable $handler): void
    {
        $this->__invoke($handler);
    }

    public function __invoke(callable $handler): void
    {
        $handler($this->createInput(), $this->createOutput());
    }

    public function isInteractive(): bool
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
     * @param callable $headerPrinter
     *
     * @return $this
     */
    public function outputHeaderBy(callable $headerPrinter) : WebserverIOAdapter
    {
        $this->headerPrinter = $headerPrinter;
        return $this;
    }

    /**
     * @param bool $fake
     *
     * @return self
     */
    public function fakeSentHeaders(bool $fake) : WebserverIOAdapter
    {
        $this->fakeSentHeaders = $fake;
        return $this;
    }

    /**
     * Set the cookie serializer.
     *
     * @param callable $cookieSerializer
     * @return $this
     */
    public function serializeCookieBy(callable $cookieSerializer) : WebserverIOAdapter
    {
        $this->cookieSerializer = $cookieSerializer;
        return $this;
    }

    /**
     * Mimic the output header for a non-http responses.
     *
     * @param Response $response
     * @return void
     */
    protected function outputHttpHeadersForCoreResponse(Response $response) : void
    {
        if ($this->headersWereSent()) {
            return;
        }

        if ($response->status > 299 && $response->status < 600) {
            $this->printHeader($this->parser->buildStatusLine($response->status));
        }

        $this->outputPropertiesAsHeaders($response, $response->envelope);
    }

    /**
     * @param ResponseInterface $response
     */
    protected function outputHttpHeaders(ResponseInterface $response)
    {
        if ($this->headersWereSent()) {
            return;
        }

        $this->printHeader($this->parser->statusLineOfResponse($response));

        $headers = $response->getHeaders();
        $this->outputPropertiesAsHeaders($response, $headers);

        foreach ($headers as $key=>$lines) {
            foreach ($lines as $header) {
                $this->printHeader("$key: $header");
            }
        }

        if (!$response instanceof HttpResponse) {
            return;
        }

        foreach ($response->cookies as $cookie) {
            $this->printHeader('Set-Cookie: ' . $this->serializeCookie($cookie));
        }
    }

    /**
     * @param string $name
     * @param bool $replace
     */
    protected function printHeader(string $name, bool $replace=true)
    {
        $handler = $this->headerPrinter ?: 'header';
        call_user_func($handler, $name, $replace);
    }

    /**
     * @return bool
     */
    protected function headersWereSent() : bool
    {
        return is_bool($this->fakeSentHeaders) ? $this->fakeSentHeaders : headers_sent();
    }

    /**
     * @param Cookie $cookie
     * @return string
     */
    protected function serializeCookie(Cookie $cookie) : string
    {
        if (!$this->cookieSerializer) {
            $this->serializeCookieBy(new CookieSerializer());
        }
        return call_user_func($this->cookieSerializer, $cookie);
    }

    /**
     * @param Response $response
     * @param array $envelope
     * @return void
     */
    protected function outputPropertiesAsHeaders(Response $response, array $envelope) : void
    {
        $lowerKeys = array_map(function ($key) {
            return strtolower($key);
        }, array_keys($envelope));

        if ($response->contentType && !in_array('content-type', $lowerKeys)) {
            $this->printHeader("Content-Type: $response->contentType; charset=".$this->guessCharset($response));
        }
    }

    /**
     * @param Response $response
     * @return string
     */
    protected function guessCharset(Response $response) : string
    {
        $payload = $response->payload;
        if (is_object($payload) && method_exists($payload, 'getCharset')) {
            return $payload->getCharset();
        }
        return  ini_get('default_charset') ?: 'UTF-8';
    }
    protected function createResource(Url $url)
    {
        return fopen($this->uri, 'r');
    }

    protected function createInput(array $serverParams=[], string $method='', Url $uri=null) : HttpInput
    {
        $server = $serverParams ?: $this->server;
        $method = $method ?: ($server['REQUEST_METHOD'] ?? 'GET');
        $headers = $this->headers ?: $this->parser->guessHeaders();
        if (in_array($method, ['PUT', 'PATCH']) && !$this->body &&
            $contentType = $this->parser->guessContentType($headers)) {
            $this->body = $this->parser->parseBodyParams(file_get_contents('php://input'), $contentType);
        }
        $url = $uri ?: $this->parser->guessUrl($server, $headers);

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return (new HttpInput($method, $url, $headers, '', $server))
            ->withQueryParams($this->query)
            ->withParsedBody($this->body)
            ->withCookieParams($this->cookies)
            ->withUploadedFiles($this->files)
            ->withDeterminedContentType('text/html');
    }

    protected function createOutput() : callable
    {
        return function ($response, bool $lock=false) {
            if (!$response instanceof Response) {
                echo $response;
                return true;
            }

            if (!$response instanceof ResponseInterface) {
                $this->outputHttpHeadersForCoreResponse($response);
                echo $response->payload;
                return true;
            }

            $this->outputHttpHeaders($response);
            echo $response->getBody();
            return true;
        };
    }
}