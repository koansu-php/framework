<?php

namespace Koansu\Core\Storages;

use Koansu\Core\Contracts\Storage;
use LogicException;

/**
 * The UnbufferedStorageProxy makes a buffered storage an unbuffered.
 * Just wrap the other one with this proxy and all changes get instantly
 * persisted.
 **/
class UnbufferedProxyStorage extends AbstractProxyStorage implements Storage
{

    /**
     * @param Storage $storage
     **/
    public function __construct(Storage $storage)
    {
        parent::__construct($storage);
        if (!$storage->isBuffered()) {
            throw new LogicException('There is no need make un unbuffered storage unbuffered.');
        }
    }

    /**
     * Put data into this storage. At least write a file.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     **/
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        parent::offsetSet($offset, $value);
        $this->storage->persist();
    }

    /**
     * Unset $offset. If the file or the directory does not exist, just ignore
     * the error
     *
     * @param string $offset
     *
     * @return void
     **/
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->storage->offsetUnset($offset);
        $this->storage->persist();
    }

    /**
     * @inheritDoc
     */
    public function isBuffered() : bool
    {
        return false;
    }

    /**
     * @param array|null $keys
     *
     **/
    public function clear(array $keys=null)
    {
        $this->storage->clear($keys);
        $this->storage->persist();
    }

}
