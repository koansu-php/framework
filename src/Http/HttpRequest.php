<?php
/**
 *  * Created by mtils on 24.10.2022 at 19:54.
 **/

namespace Koansu\Http;

use Koansu\Core\Message;
use Koansu\Routing\Contracts\Input;
use Koansu\Core\ImmutableMessage;
use Koansu\Core\Url;
use Koansu\Http\Psr\PsrMessageTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use function is_array;
use function property_exists;

/**
 * @property-read string protocolVersion
 * @property-read array headers
 * @property-read StreamInterface body
 * @property-read string requestTarget
 * @property-read string method
 * @property-read UriInterface|Url uri
 * @property-read UriInterface|Url url
 */
class HttpRequest extends ImmutableMessage implements RequestInterface
{
    use PsrMessageTrait;

    /**
     * @var string
     */
    protected $requestTarget = '';

    /**
     * @var string
     */
    protected $method = Input::GET;

    /**
     * @var UriInterface
     */
    protected $uri;

    public function __construct($data = [], array $headers=[], Url $url=null)
    {
        $this->transport = Message::TRANSPORT_NETWORK;
        $this->uri = $url ?: new Url();

        if ($headers || !is_array($data)) {
            $data = [
                'payload' => $data,
                'envelope' => $headers
            ];
        }

        if (is_array($data) && isset($data['headers'])) {
            $data['envelope'] = $data['headers'];
        }

        if (isset($data['payload']) && is_array($data['payload']) && !isset($data['custom'])) {
            $data['custom'] = $data['payload'];
        }
        parent::__construct($data);
        $this->apply($data);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        switch ($key) {
            case 'protocolVersion':
                return $this->protocolVersion;
            case 'body':
                return $this->getBody();
            case 'headers':
                return $this->envelope;
            case 'requestTarget':
                return $this->getRequestTarget();
            case 'method':
                return $this->getMethod();
            case 'uri':
            case 'url':
                return $this->getUri();
        }
        return parent::__get($key);
    }

    /**
     *
     * @return string
     */
    public function getRequestTarget() : string
    {
        if ($this->requestTarget) {
            return $this->requestTarget;
        }
        $path = $this->uri->getPath();
        $path = $path === '' ? '/' : $path;
        $query = $this->uri->getQuery();
        $this->requestTarget = $path . ($query ? "?$query" : '');
        return $this->requestTarget;
    }

    /**
     * @param string $requestTarget
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withRequestTarget($requestTarget)
    {
        return $this->replicate(['requestTarget' => $requestTarget]);
    }

    /**
     * @return string
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * @param $method
     * @return ImmutableMessage|HttpRequest
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withMethod($method)
    {
        return $this->replicate(['method' => $method]);
    }

    /**
     * @return Url|UriInterface
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getUri()
    {
        if ($this->uri) {
            return $this->uri;
        }
        return new Url();
    }

    /**
     * @param UriInterface $uri
     * @param $preserveHost
     * @return ImmutableMessage|HttpRequest
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        return $this->replicate(['uri' => $uri, 'preserveHost' => $preserveHost]);
    }

    protected function apply(array $attributes)
    {
        if (isset($attributes['url']) && !isset($attributes['uri'])) {
            $attributes['uri'] = $attributes['url'];
            unset($attributes['url']);
        }
        if (isset($attributes['uri']) && !$attributes['uri'] instanceof Url) {
            unset($attributes['uri']);
        }
        foreach ($attributes as $property=>$value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    protected function copyStateInto(array &$attributes)
    {

        if (!isset($attributes['protocolVersion'])) {
            $attributes['protocolVersion'] = $this->protocolVersion;
        }

        if (!isset($attributes['requestTarget'])) {
            $attributes['requestTarget'] = $this->requestTarget;
        }

        if (!isset($attributes['method'])) {
            $attributes['method'] = $this->method;
        }

        if (!isset($attributes['uri'])) { //} && !isset($attributes['url'])) {
            $attributes['uri'] = $this->uri;
        }

        parent::copyStateInto($attributes);
    }

}