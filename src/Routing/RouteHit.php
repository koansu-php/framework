<?php
/**
 *  * Created by mtils on 23.10.2022 at 20:36.
 **/

namespace Koansu\Routing;

use ArrayAccess;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\Core\Contracts\Arrayable;

use function array_key_exists;
use function get_class;

/**
 * Class RouteHit
 *
 * A RouteHit is a result of matching a route by a Dispatcher. ArrayAccess
 * is o access the route parameters
 *
 * @property-read string $method     The (http) method which did apply
 * @property-read string $pattern    The pattern that was registered and did apply
 * @property-read mixed  $handler    The handler that was registered
 * @property-read array  $parameters The parsed parameters
 */
class RouteHit implements Arrayable, ArrayAccess
{
    protected $_properties = [
        'method'    => '',
        'pattern'   => '',
        'handler'   => null,
        'parameters'=> []
    ];

    /**
     * RouteHit constructor.
     *
     *
     * @param string $method
     * @param string $pattern
     * @param mixed  $handler
     * @param array  $parameters (optional)
     */
    public function __construct(string $method, string $pattern, $handler, array $parameters=[])
    {
        $this->_properties = [
            'method'     => $method,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'parameters' => $parameters
        ];
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (isset($this->_properties[$name])) {
            return $this->_properties[$name];
        }

        if (!array_key_exists($name, $this->_properties)) {
            throw new KeyNotFoundException("Property $name not found in " . get_class($this));
        }

        return null;
    }

    /**
     * is triggered by calling isset() or empty() on inaccessible members.
     *
     * @param $name string
     * @return bool
     *
     * @link https://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
     */
    public function __isset(string $name) : bool
    {
        return array_key_exists($name, $this->_properties);
    }

    /**
     * Overwritten because it would be confusing to only get the parameters if
     * you call toArray on the match.
     *
     * @return array
     *
     * @see Arrayable
     **/
    public function __toArray() : array
    {
        return $this->_properties;
    }

    /**
     * Check if the route parameter $offset was set.
     *
     * @param mixed $offset
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function offsetExists($offset) : bool
    {
        return array_key_exists($offset, $this->_properties['parameters']);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (isset($this->_properties['parameters'][$offset])) {
            return $this->_properties['parameters'][$offset];
        }
        return null;
    }

    /**
     * Set the value of $offset.
     *
     * @param mixed $offset
     * @param mixed $value
     **/
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->_properties['parameters'][$offset] = $value;
    }

    /**
     * Unset $offset.
     *
     * @param mixed $offset
     **/
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->_properties['parameters'][$offset]);
    }
}