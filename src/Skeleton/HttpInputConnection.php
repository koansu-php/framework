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
use OutOfBoundsException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function file_get_contents;
use function function_exists;
use function getallheaders;
use function json_decode;
use function parse_str;
use function strpos;
use function strtolower;

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
        $method = $method ?: $server['REQUEST_METHOD'];
        $headers = $this->headers ?: $this->guessHeaders();

        if (in_array($method, ['PUT', 'PATCH']) && !$this->body) {
            $this->body = $this->parseBodyParams($headers);
        }

        return (new HttpInput($method, $uri ?: $this->createUrl($server), $headers, '', $server))
            ->withQueryParams($this->query)
            ->withParsedBody($this->body)
            ->withCookieParams($this->cookies)
            ->withUploadedFiles($this->files)
            ->withDeterminedContentType('text/html');

    }

    protected function parseBodyParams(array $headers) : array
    {
        if (!$contentType = $this->guessContentType($headers)) {
            throw new OutOfBoundsException('No valid content type could be detected to build body params');
        }
        if ($contentType == 'application/x-www-form-urlencoded') {
            $data = [];
            parse_str(file_get_contents('php://input'), $data);
            return $data;
        }
        if (strpos($contentType, 'multipart/form-data') === 0) {
            return $this->parseRawBody(file_get_contents('php://input'));
        }
        if (strpos($contentType, 'application/json') === 0) {
            return json_decode(file_get_contents('php://input'), true);
        }
        return [];
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
        if (!function_exists('getallheaders')) {
            return [];
        }
        return getallheaders() ?: [];
    }

    protected function guessContentType(array $headers) : string
    {
        foreach ($headers as $name=>$value) {
            if (strtolower($name) == 'content-type') {
                return strtolower($value);
            }
        }
        return '';
    }

    protected function parseRawBody(string $rawBody) : array
    {
        $params = [];

        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        $boundary = $matches[1];

        // split content by boundary and get rid of last -- element
        $blocks = preg_split("/-+$boundary/", $rawBody);
        array_pop($blocks);

        // loop data blocks
        foreach ($blocks as $id => $block)
        {
            if (empty($block))
                continue;

            // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

            // parse uploaded files
            if (strpos($block, 'application/octet-stream') !== FALSE)
            {
                // match "name", then everything after "stream" (optional) except for prepending newlines
                preg_match('/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $block, $matches);
            }
            // parse all other fields
            else
            {
                // match "name" and optional value in between newline sequences
                preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
            }
            $params[$matches[1]] = $matches[2];
        }

        return $params;
    }
}