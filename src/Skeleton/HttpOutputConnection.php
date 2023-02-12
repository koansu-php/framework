<?php
/**
 *  * Created by mtils on 03.12.2022 at 13:48.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\AbstractConnection;
use Koansu\Core\Response;
use Koansu\Core\Url;
use Koansu\Http\Cookie;
use Koansu\Http\CookieSerializer;
use Koansu\Http\HttpResponse;
use Koansu\Skeleton\Contracts\OutputConnection;
use Psr\Http\Message\ResponseInterface;

use function fwrite;

use const STDOUT;

class HttpOutputConnection extends AbstractConnection implements OutputConnection
{
    /**
     * @var Url
     */
    protected $url;

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var string
     */
    protected $uri = 'php://output';

    /**
     * @var bool|null
     */
    protected $fakeSentHeaders;

    /**
     * @var callable
     */
    protected $headerPrinter;

    /**
     * @var callable
     */
    protected $cookieSerializer;

    /**
     * Write the output. Usually just echo it
     *
     * @param string|object $output
     * @param bool $lock
     *
     * @return mixed
     */
    public function write($output, bool $lock = false) : bool
    {
        if (!$output instanceof Response) {
            return (bool)fwrite($this->resource(), $output);
        }

        if (!$output instanceof ResponseInterface) {
            $this->outputHttpHeadersForCoreResponse($output);
            return (bool)fwrite($this->resource(), $output->payload);
        }

        $this->outputHttpHeaders($output);
        return (bool)fwrite($this->resource(), $output->getBody());

    }

    /**
     * @param callable $headerPrinter
     *
     * @return $this
     */
    public function outputHeaderBy(callable $headerPrinter) : HttpOutputConnection
    {
        $this->headerPrinter = $headerPrinter;
        return $this;
    }

    /**
     * @param bool $fake
     *
     * @return self
     */
    public function fakeSentHeaders(bool $fake) : HttpOutputConnection
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
    public function serializeCookieBy(callable $cookieSerializer) : HttpOutputConnection
    {
        $this->cookieSerializer = $cookieSerializer;
        return $this;
    }

    public function close(): void
    {
        if ($this->uri == 'php://output') {
            return; // STDOUT cannot be closed
        }
        parent::close();
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
            $this->printHeader($this->buildStatusLine($response->status));
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

        $this->printHeader($this->getStatusLine($response));

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
     * @param Url $url
     *
     * @return resource
     */
    protected function createResource(Url $url)
    {
        if ($this->uri == 'php://stdout') {
            return STDOUT;
        }
        return fopen($this->uri, 'w');
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
     * @param ResponseInterface $response
     * @return string
     */
    protected function getStatusLine(ResponseInterface $response) : string
    {
        $protocolVersion = $response->getProtocolVersion() ?: '1.1';
        $statusCode = $response->getStatusCode() ?: 200;
        $statusPhrase = $response->getReasonPhrase() ?: '';
        return $this->buildStatusLine($statusCode, $protocolVersion, $statusPhrase);

    }

    /**
     * Build the http status line
     *
     * @param int $status
     * @param string $protocolVersion
     * @param string $reasonPhrase
     *
     * @return string
     */
    protected function buildStatusLine(int $status=200, string $protocolVersion='1.1', string $reasonPhrase='') : string
    {
        return trim("HTTP/$protocolVersion $status $reasonPhrase");
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
}