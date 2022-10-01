<?php
/**
 *  * Created by mtils on 24.03.19 at 10:54.
 **/

namespace Koansu\Core\Storages;


use ArrayIterator;
use IteratorAggregate;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Storage;

use function array_key_exists;

class ArrayStorage implements Storage, IteratorAggregate, Arrayable
{
    /**
     * @var array
     **/
    protected $attributes = [];

    /**
     * ArrayStorage constructor.
     *
     * @param array $data
     */
    public function __construct(array $data=[])
    {
        $this->attributes = $data;
    }

    /**
     * @return string
     */
    public function storageType() : string
    {
        return Storage::MEMORY;
    }

    /**
     * Check if $offset exists.
     *
     * @param mixed $offset
     *
     * @return bool
     **/
    public function offsetExists($offset) : bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get value of $offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     **/
    public function offsetGet($offset)
    {
        return $this->attributes[$offset];
    }

    /**
     * Set the value of $offset.
     *
     * @param mixed $offset
     * @param mixed $value
     **/
    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset $offset.
     *
     * @param mixed $offset
     **/
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * @return bool
     */
    public function persist() : bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isBuffered() : bool
    {
        return false;
    }

    /**
     * Clears the internal array
     *
     * @param array|null $keys (optional)
     *
     * @return void
     **/
    public function clear(array $keys=null)
    {
        if ($keys === null) {
            $this->attributes = [];
            return;
        }

        if (!$keys) {
            return;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->attributes)) {
                $this->offsetUnset($key);
            }
        }
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->attributes);
    }

    public function __toArray(): array
    {
        return $this->attributes;
    }

}