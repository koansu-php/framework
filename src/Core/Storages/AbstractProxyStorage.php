<?php
/**
 *  * Created by mtils on 09.09.18 at 10:30.
 **/

namespace Koansu\Core\Storages;


use Koansu\Core\Contracts\Storage;

abstract class AbstractProxyStorage implements Storage
{
    /**
     * @var Storage
     **/
    protected $storage;

    /**
     * @param Storage $storage
     **/
    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Return if the key $key does exist. At the end if the file exists.
     *
     * @param string $offset
     *
     * @return bool
     **/
    public function offsetExists($offset) : bool
    {
        return $this->storage->offsetExists($offset);
    }

    /**
     * Return the data of $offset. No error handling is done here. You have to
     * catch the filesystem exceptions by yourself.
     *
     * @param string $offset
     *
     * @return mixed
     **/
    public function offsetGet($offset)
    {
        return $this->storage->offsetGet($offset);
    }

    /**
     * Put data into this storage. At least write a file.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     **/
    public function offsetSet($offset, $value)
    {
        $this->storage->offsetSet($offset, $value);
    }

    /**
     * Unset $offset. If the file or the directory does not exist, just ignore
     * the error
     *
     * @param string $offset
     *
     * @return void
     **/
    public function offsetUnset($offset)
    {
        $this->storage->offsetUnset($offset);
    }

    /**
     * @param array|null $keys
     *
     * @return void
     **/
    public function clear(array $keys=null)
    {
        $this->storage->clear($keys);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    public function storageType() : string
    {
        return self::UTILITY;
    }

    /**
     * @inheritDoc
     */
    public function isBuffered() : bool
    {
        return $this->storage->isBuffered();
    }

    /**
     * @inheritDoc
     */
    public function persist() : bool
    {
        return $this->storage->persist();
    }

}