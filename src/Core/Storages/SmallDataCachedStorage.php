<?php
/**
 *  * Created by mtils on 13.06.19 at 13:07.
 **/

namespace Koansu\Core\Storages;


use ArrayIterator;
use IteratorAggregate;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Storage;
use Koansu\Core\Exceptions\KeyNotFoundException;
use TypeError;


/**
 * Class SmallDataCachedStorage
 *
 * This is cache storage for small data storage. If you know that it is the most
 * effective way to just read the whole data and cache it instead of caching
 * single entries use this one.
 */
class SmallDataCachedStorage extends AbstractProxyStorage implements IteratorAggregate, Arrayable
{
    /**
     * @var ?array
     */
    protected $cache;

    /**
     * @var Storage|Arrayable
     */
    protected $storage;

    /**
     * @param Storage|Arrayable $storage
     */
    public function __construct(Storage $storage)
    {
        if (!$storage instanceof Arrayable) {
            throw new TypeError('The passed storage has to be instance of ' . Arrayable::class);
        }
        parent::__construct($storage);
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
        $all = $this->__toArray();
        return isset($all[$offset]);
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
        $all = $this->__toArray();
        if (isset($all[$offset])) {
            return $all[$offset];
        }
        throw new KeyNotFoundException("Key $offset not found");
    }

    /**
     * Put data into this storage. At least write a file.
     *
     * @param string $offset
     * @param mixed $value
     *
     * @return void
     **/
    public function offsetSet($offset, $value)
    {
        parent::offsetSet($offset,$value);
        $this->invalidateCache();
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
        parent::offsetUnset($offset);
        $this->invalidateCache();
    }

    /**
     * {@inheritDoc}
     *
     * @param array|null $keys
     *
     * @return void
     **/
    public function clear(array $keys = null)
    {
        parent::clear($keys);
        $this->invalidateCache();
    }

    /**
     * {@inheritdoc}
     *
     * CAUTION: Be careful with this method! You will perhaps end up in filling
     * your whole memory with this.
     *
     * @return array
     **/
    public function __toArray() : array
    {
        if ($this->cache === null) {
            $this->cache = $this->storage->__toArray();
        }
        return $this->cache;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->__toArray());
    }


    /**
     * Delete the cache
     */
    protected function invalidateCache()
    {
        $this->cache = null;
    }

}