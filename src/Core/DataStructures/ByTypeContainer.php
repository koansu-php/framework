<?php
/**
 *  * Created by mtils on 30.08.20 at 08:49.
 **/

namespace Koansu\Core\DataStructures;


use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Koansu\Core\Type;

use function array_keys;

/**
 * Class ByTypeContainer
 *
 * This container is a utility to store handlers or any data by type inheritance.
 * So in a case you need "$this is the handler for objects of this class or this
 * interface" this could be the right utility for this use case.
 */
class ByTypeContainer implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array
     */
    protected $extensions = [];

    /**
     * @var array
     */
    protected $forInstanceOfCache = [];

    public function __construct(array $extensions=[])
    {
        $this->extensions = $extensions;
    }

    /**
     * Find the handler/data for $class. Check by inheritance.
     *
     * @param string $class
     *
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function forInstanceOf(string $class)
    {
        if (isset($this->forInstanceOfCache[$class])) {
            return $this->forInstanceOfCache[$class];
        }
        if (isset($this->extensions[$class])) {
            $this->forInstanceOfCache[$class] = $this->extensions[$class];
            return $this->extensions[$class];
        }
        foreach (Type::filterToParents(array_keys($this->extensions), $class) as $abstract) {
            $this->forInstanceOfCache[$class] = $this->extensions[$abstract];
            return $this->extensions[$abstract];
        }
        return null;
    }

    /**
     * Whether an offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return bool true on success or false on failure.
     * </p>
     * <p>
     * The return value will be cast to boolean if non-boolean was returned.
     * @noinspection PhpMissingParamTypeInspection
     */
    public function offsetExists($offset) : bool
    {
        return isset($this->extensions[$offset]);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->extensions[$offset];
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->extensions[$offset] = $value;
        $this->forInstanceOfCache = [];
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->extensions[$offset]);
        $this->forInstanceOfCache = [];
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->extensions);
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return count($this->extensions);
    }

}