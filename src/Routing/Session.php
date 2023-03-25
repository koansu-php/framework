<?php
/**
 *  * Created by mtils on 30.10.2022 at 17:24.
 **/

namespace Koansu\Routing;

use ArrayAccess;
use Koansu\Core\Contracts\Arrayable;

use function array_key_exists;

class Session implements Arrayable, ArrayAccess
{
    /**
     * @var array|null
     */
    protected $data;

    /**
     * @var string
     */
    protected $id = '';

    public function __construct(array $data=null, string $id='')
    {
        $this->data = $data;
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return Session
     */
    public function setId(string $id): Session
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return array
     */
    public function __toArray() : array
    {
        return $this->data;
    }

    public function offsetExists($offset) : bool
    {
        return array_key_exists($offset, $this->data);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @param array|null $keys
     * @return $this
     */
    public function clear(array $keys = null) : Session
    {
        if ($keys === []) {
            return $this;
        }

        if ($keys === null) {
            $this->data = [];
            return $this;
        }

        foreach ($keys as $key) {
            $this->remove($key);
        }
        return $this;
    }

    protected function remove($key)
    {
        if (isset($this->data[$key])) {
            unset($this->data[$key]);
        }
    }

}