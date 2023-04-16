<?php
/**
 *  * Created by mtils on 14.04.2023 at 09:23.
 **/

namespace Koansu\Http;

use Koansu\Core\Url;
use OutOfBoundsException;

use Psr\Http\Message\ResponseInterface;

use function array_pop;
use function function_exists;
use function getallheaders;
use function is_array;
use function json_decode;
use function ltrim;
use function parse_str;
use function preg_match;
use function preg_split;
use function strpos;
use function strtolower;
use function substr;
use function trim;

class HttpParser
{
    public function guessUrl(array $server, array $headers=[]) : Url
    {
        $port = $server['SERVER_PORT'] ?? null;
        /** @noinspection HttpUrlsUsage */
        $protocol = ((!empty($server['HTTPS']) && $server['HTTPS'] != 'off') || $port == 443) ? "https://" : "http://";

        $path = isset($server['REQUEST_URI']) ? ('/' . ltrim($server['REQUEST_URI'],'/')) : '';
        $url = $protocol . $this->getHostFromHeaders($headers) . $path;

        return new Url($url);
    }

    public function guessContentType(array $headers) : string
    {
        return $this->headerValue($headers, 'content-type');
    }

    public function headerValue(array $headers, string $header) : string
    {
        $lowerHeader = strtolower($header);
        foreach ($headers as $name=>$lines) {
            if (strtolower($name) != $lowerHeader) {
                continue;
            }
            return is_array($lines) ? implode("\n", $lines) : $lines;
        }
        return '';
    }

    public function guessHeaders() : array
    {
        if (!function_exists('getallheaders')) {
            return [];
        }
        return getallheaders() ?: [];
    }

    public function parseBodyParams(string $body, string $contentType) : array
    {
        if ($contentType == 'application/x-www-form-urlencoded') {
            $data = [];
            parse_str($body, $data);
            return $data;
        }
        if (strpos($contentType, 'multipart/form-data') === 0) {
            return $this->parseMultipartBody($body);
        }
        if (strpos($contentType, 'application/json') === 0) {
            return json_decode($body, true);
        }
        throw new OutOfBoundsException("Content type $contentType is not supported");
    }

    public function parseMultipartBody(string $rawBody) : array
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

    public function getHostFromHeaders(array $headers) : string
    {
        if (!$hostAndPort = $this->headerValue($headers, 'host')) {
            throw new OutOfBoundsException('Host header not found');
        }
        if ($pos = strpos($hostAndPort, ':')) {
            return substr($hostAndPort, 0, $pos);
        }
        return $hostAndPort;
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
    public function buildStatusLine(int $status=200, string $protocolVersion='1.1', string $reasonPhrase='') : string
    {
        return trim("HTTP/$protocolVersion $status $reasonPhrase");
    }

    /**
     * @param ResponseInterface $response
     * @return string
     */
    public function statusLineOfResponse(ResponseInterface $response) : string
    {
        $protocolVersion = $response->getProtocolVersion() ?: '1.1';
        $statusCode = $response->getStatusCode() ?: 200;
        $statusPhrase = $response->getReasonPhrase() ?: '';
        return $this->buildStatusLine($statusCode, $protocolVersion, $statusPhrase);
    }
}