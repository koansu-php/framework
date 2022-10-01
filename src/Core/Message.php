<?php
/**
 *  * Created by mtils on 24.08.19 at 07:37.
 **/

namespace Koansu\Core;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Countable;
use Iterator;
use IteratorAggregate;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Exceptions\ItemNotFoundException;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\Core\Exceptions\SymbolNotFoundException;
use TypeError;

use function array_key_exists;
use function is_bool;

/**
 * Class AbstractMessage
 *
 * A message is a value object to send from an emitter to a listener
 * (publisher>subscriber|handler>receiver). This is the base for input (requests), responses...
 *
 * The class holds the payload data in payload. This could be a string or whatever
 * raw (binary) data.
 * The ArrayAccess interface and toArray() gives access to "payload data". If
 * you receive json data the json formatted string would be in payload, the
 * data will be accessible via ArrayAccess/toArray().
 *
 * So often you would parse the received data, turn it into an array and use the
 * message like an array.
 *
 * Regarding events: In general better connect signatures instead of writing
 * event classes.
 *
 * @property-read string type The type Message::TYPE_INPUT|Message::TYPE_OUTPUT|Message::TYPE_LOG
 * @property-read bool accepted
 * @property-read bool ignored
 * @property-read string transport The transport media to send the message
 * @property-read array custom The manually set attributes
 * @property-read array envelope The metadata of this message like http headers
 * @property-read mixed payload The raw payload
 */
abstract class Message implements ArrayAccess, IteratorAggregate, Countable, Arrayable
{
    /**
     * Type input
     */
    public const TYPE_INPUT = 'input';

    /**
     * Type output
     */
    public const TYPE_OUTPUT = 'output';

    /**
     * Type log
     */
    public const TYPE_LOG = 'log';

    /**
     * Type event
     */
    public const TYPE_EVENT = 'event';

    /**
     * Type custom
     */
    public const TYPE_CUSTOM = 'custom';

    /**
     * Sent through network
     */
    public const TRANSPORT_NETWORK = 'network';

    /**
     * Sent through terminal (tty)
     */
    public const TRANSPORT_TERMINAL = 'terminal';

    /**
     * Sent through Inter Process Communication
     */
    public const TRANSPORT_IPC = 'ipc';

    /**
     * Sent internally in application
     */
    public const TRANSPORT_APP = 'app';

    /**
     * The custom attribute "pool". All manually added attributes.
     */
    public const POOL_CUSTOM = 'custom';

    public const POOL_GET = 'get';

    public const POOL_POST = 'post';

    public const POOL_COOKIE = 'cookie';

    public const POOL_FILES = 'files';

    public const POOL_SERVER = 'server';

    public const POOL_ARGV = 'argv';

    public const POOL_ENV = 'env';

    public const POOL_ROUTE = 'route';

    /**
     * @var string
     */
    protected $type = self::TYPE_CUSTOM;

    /**
     * @var bool|null
     */
    protected $accepted;

    /**
     * @var string
     */
    protected $transport = self::TRANSPORT_APP;

    /**
     * @var array
     */
    protected $custom = [];

    /**
     * @var array
     */
    protected $envelope = [];

    /**
     * @var mixed
     */
    protected $payload;

    /**
     * @param array|mixed $payload Pass an array to set custom attributes, everything else
     * @param array $envelope
     * @param string $type (optional)
     * @param string $transport
     */
    public function __construct($payload=[], array $envelope=[], string $type=self::TYPE_CUSTOM, string $transport=self::TRANSPORT_APP)
    {
        $this->payload = $payload;
        if (is_array($payload)) {
            $this->custom = $payload;
        }
        $this->envelope = $envelope;
        $this->type = $type;
        $this->transport = $transport;
    }

    /**
     * Get a value from (parsed) attributes.
     *
     * @param string $id
     * @param mixed $default
     * @return mixed|null
     */
    public function get(string $id, $default = null)
    {
        if (array_key_exists($id, $this->custom)) {
            return $this->custom[$id];
        }
        return $default;
    }

    /**
     * @param string $id
     * @return mixed|null
     *
     * @throws ItemNotFoundException
     */
    public function getOrFail(string $id)
    {
        $value = $this->get($id, new None());
        if ($value instanceof None) {
            throw new KeyNotFoundException("Attribute $id no found");
        }
        return $value;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        switch ($key) {
            case 'type':
                return $this->type;
            case 'accepted':
                return $this->isAccepted();
            case 'ignored':
                return $this->isIgnored();
            case 'transport':
                return $this->transport;
            case 'custom':
                return $this->__toArray();
            case 'envelope':
                return $this->envelope;
            case 'payload':
                return $this->payload;
        }
        throw new SymbolNotFoundException($key, SymbolNotFoundException::PROPERTY_NOT_FOUND);
    }

    /**
     * Return true if the input was accepted. If nobody accepted it this returns
     * false even it was not ignored.
     *
     * @return bool
     */
    public function isAccepted() : bool
    {
        return is_bool($this->accepted) && $this->accepted;
    }

    /**
     * Return true if the input was ignored. If nobody ignored it this returns
     * false even it was not accepted.
     *
     * @return bool
     */
    public function isIgnored() : bool
    {
        return is_bool($this->accepted) && !$this->accepted;
    }

    /**
     * Mark the message as accepted.
     *
     * @return self
     */
    public function accept() : Message
    {
        $this->accepted = true;
        return $this;
    }

    /**
     * Mark the message as ignored.
     *
     * @return self
     */
    public function ignore() : Message
    {
        $this->accepted = false;
        return $this;
    }

    /**
     * @param string|int $offset
     * @return bool
     */
    public function offsetExists($offset) : bool
    {
        return isset($this->custom[$offset]);
    }

    /**
     * @param string|int $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->custom[$offset];
    }

    /**
     * @param string|int $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('Setting values is not supported by AbstractMessage');
    }

    /**
     * @param string|int $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('Unsetting values is not supported by AbstractMessage');
    }

    /**
     * @return array
     */
    public function __toArray() : array
    {
        return $this->custom;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator() : Iterator
    {
        return new ArrayIterator($this->__toArray());
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return count($this->__toArray());
    }

    /**
     * Check and save accepted property.
     *
     * @param mixed $value
     * @return void
     */
    protected function setAccepted($value)
    {
        if (!is_null($value) && !is_bool($value)) {
            throw new TypeError('accepted can only be boolean or null');
        }
        $this->accepted = $value;
    }
}