<?php /** @noinspection PhpStrFunctionsInspection */

/**
 *  * Created by mtils on 27.10.2022 at 17:20.
 **/

namespace Koansu\Http;

use DateTime;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Serializer as SerializerContract;
use Koansu\Core\Exceptions\ConfigurationException;
use Koansu\Core\Message;
use Koansu\Core\Response;
use Koansu\Core\Serializer;
use Koansu\Core\Str;
use Koansu\Core\Stream;
use Koansu\Core\Type;
use Koansu\Http\Psr\PsrMessageTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use stdClass;
use Traversable;
use TypeError;
use UnexpectedValueException;

use function call_user_func;
use function explode;
use function func_num_args;
use function is_array;
use function is_numeric;
use function iterator_to_array;
use function strpos;
use function strtolower;
use function trim;

/**
 * @property-read string protocolVersion
 * @property-read array headers
 * @property-read StreamInterface body
 * @property-read string raw The raw http request string with headers and body
 * @property-read array|Cookie[] cookies
 * @property-read bool secureCookies
 */
class HttpResponse extends Response implements ResponseInterface
{
    use PsrMessageTrait;

    /**
     * @var Serializer|null
     */
    protected $serializer;

    /**
     * @var callable|null
     */
    protected $serializerFactory;

    /**
     * @var bool
     */
    protected $payloadDeserialized = false;

    /**
     * @var string
     */
    protected $raw;

    /**
     * @var array|Cookie[]
     */
    protected $cookies = [];

    /**
     * @var bool
     */
    protected $secureCookies = true;

    public function __construct($data = null, array $headers=[], int $status=200, string $contentType='text/html')
    {
        parent::__construct($data, $headers, $status, $contentType);
        $this->transport = Message::TRANSPORT_NETWORK;
        $this->applyEnvelope($headers);
    }

    /**
     * @return int
     */
    public function getStatusCode() : int
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getReasonPhrase() : string
    {
        return $this->statusMessage;
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
            case 'raw':
                return $this->raw;
            case 'cookies':
                return $this->cookies;
            case 'secureCookies':
                return $this->secureCookies;
        }
        return parent::__get($key);
    }

    /**
     * @return StreamInterface|Stream
     */
    public function getBody() : StreamInterface
    {
        if ($this->payload instanceof StreamInterface) {
            return $this->payload;
        }
        return new Stream(new Str($this->__toString()));
    }


    public function __toString()
    {
        if (Type::isStringable($this->payload)) {
            return (string)$this->payload;
        }
        return $this->serializePayload($this->payload);
    }

    /**
     * @return array
     */
    public function __toArray(): array
    {
        $this->tryDeserializePayload();
        return parent::__toArray();
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function get(string $key, $default = null)
    {
        $this->tryDeserializePayload();
        return parent::get($key, $default);
    }


    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        $this->tryDeserializePayload();
        return parent::offsetExists($offset);
    }

    /**
     * @param $offset
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $this->tryDeserializePayload();
        return parent::offsetGet($offset);
    }

    /**
     * @param string|int $offset
     * @param mixed $value
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->tryDeserializePayload();
        parent::offsetSet($offset, $value);
    }

    /**
     * @param $offset
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->tryDeserializePayload();
        parent::offsetUnset($offset);
    }

    public function withRaw($raw) : HttpResponse
    {
        return $this->replicate(['raw' => $raw]);
    }

    /**
     * @param string|Cookie $cookie
     * @param string|null   $value
     * @param int|DateTime  $expire
     * @param string        $path
     * @param string|null   $domain
     * @param bool|null     $secure
     * @param bool          $httpOnly
     * @param string        $sameSite
     * @return self
     * @noinspection PhpMissingParamTypeInspection
     */
    public function withCookie($cookie, string $value=null, $expire=null, string $path='/', string $domain=null, bool $secure=null, bool $httpOnly=true, string $sameSite=Cookie::LAX) : HttpResponse
    {
        $secure = $secure === null ? $this->secureCookies : $secure;
        $cookie = $cookie instanceof Cookie ? $cookie : new Cookie($cookie, $value, $expire, $path, $domain, $secure, $httpOnly, $sameSite);
        $cookies = $this->cookies;
        $cookies[$cookie->name] = $cookie;
        return $this->replicate(['cookies' => $cookies]);
    }

    /**
     * @param string|Cookie $cookie
     * @return self
     * @noinspection PhpMissingParamTypeInspection
     */
    public function withoutCookie($cookie) : HttpResponse
    {
        $name = $cookie instanceof Cookie ? $cookie->name : $cookie;
        $cookies = $this->cookies;
        if (isset($cookies[$name])) {
            unset($cookies[$name]);
        }
        return $this->replicate(['cookies' => $cookies]);
    }

    /**
     * Set the default for created cookies.
     *
     * @param bool $secure
     * @return HttpResponse
     */
    public function withSecureCookies(bool $secure) : HttpResponse
    {
        if (!$this->cookies) {
            return $this->replicate(['secureCookies' => $secure]);
        }
        $cookies = [];
        foreach ($this->cookies as $cookie) {
            $clone = clone $cookie;
            $clone->secure = $secure;
            $cookies[$cookie->name] = $clone;
        }
        return $this->replicate(['secureCookies' => $secure, 'cookies' => $cookies]);
    }

    /**
     * Assign a callable that creates serializers to serialize and deserialize
     * data.
     *
     * @param callable $callable
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function provideSerializerBy(callable $callable)
    {
        $this->serializerFactory = $callable;
    }

    protected function applyEnvelope(array $envelope) : bool
    {
        $this->envelope = [];
        $statusSet = false;
        foreach ($envelope as $key=>$value) {
            if (!is_numeric($key)) {
                $this->applyEnvelopeValue($key, $value);
                continue;
            }
            if (!$this->isStatusHeaderLine($value)) {
                $keyAndValue = explode(':', trim($value), 2);
                $this->applyEnvelopeValue(trim($keyAndValue[0]), trim($keyAndValue[1]));
                continue;
            }
            $this->status = $this->getStatusFromHeaderLine($value);
            $this->protocolVersion = $this->getProtocolVersionFromHeaderLine($value);
            $statusSet = true;
        }
        return $statusSet;
    }

    /**
     * @param string $headerLine
     * @return bool
     */
    protected function isStatusHeaderLine(string $headerLine) : bool
    {
        return strpos($headerLine, 'HTTP/') === 0;
    }

    /**
     * @param string $statusLine
     * @return int
     */
    protected function getStatusFromHeaderLine(string $statusLine) : int
    {
        $parts = explode(' ', trim($statusLine));
        return (int)trim($parts[1]);
    }

    /**
     * @param string $statusLine
     * @return string
     */
    protected function getProtocolVersionFromHeaderLine(string $statusLine) : string
    {
        $parts = explode('/', trim($statusLine), 2);
        return trim(explode(' ', $parts[1])[0]);
    }

    protected function applyEnvelopeValue($key, $value)
    {
        $lowerKey = strtolower($key);
        $this->envelope[$key] = $value;
        if ($lowerKey == 'content-type') {
            $this->contentType = $value;
        }
    }

    /**
     * @param mixed $payload
     * @return string
     */
    protected function serializePayload($payload) : string
    {
        if (!$serializer = $this->createSerializer($this->contentType)) {
            throw new ConfigurationException('You try to convert an array into string but didnt assign a serializer to handle ' . $this->contentType);
        }
        return $serializer->serialize($payload);
    }

    /**
     * @param string $contentType
     * @return Serializer|null
     */
    protected function createSerializer(string $contentType) : ?SerializerContract
    {
        if (!$this->serializerFactory) {
            return null;
        }
        $serializer = call_user_func($this->serializerFactory, $contentType);
        if ($serializer instanceof SerializerContract) {
            return $serializer;
        }
        throw new UnexpectedValueException("The assigned serializer factory has to create instance of " . Serializer::class . ' not ' . Type::of($serializer));
    }

    protected function tryDeserializePayload()
    {
        if ($this->payloadDeserialized || !$this->payload || $this->custom) {
            return;
        }
        if (strpos($this->contentType, 'text/') === 0) {
            $this->custom = [];
            $this->payloadDeserialized = true;
            return;
        }
        if (!$serializer = $this->createSerializer($this->contentType)) {
            throw new ConfigurationException('You try to deserialize a string into data but didnt assign a deserializer for ' . $this->contentType);
        }
        $this->custom = $this->castDeserializedToArray($serializer->deserialize($this->payload));
        $this->payloadDeserialized = true;
    }

    /**
     * @param $payload
     * @return array
     */
    protected function castDeserializedToArray($payload) : array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if ($payload instanceof stdClass) {
            return (array)$payload;
        }
        if ($payload instanceof Arrayable) {
            return $payload->__toArray();
        }
        if ($payload instanceof Traversable) {
            return iterator_to_array($payload);
        }

        throw new TypeError('Cannot cast deserialized data ito array, its: ' . Type::of($payload));
    }
}