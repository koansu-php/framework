<?php
/**
 *  * Created by mtils on 18.12.2022 at 08:41.
 **/

namespace Koansu\Filesystem\Storages;

use Koansu\Core\ConfigurableTrait;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Storage;
use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Core\Url;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Core\Serializer;
use Koansu\Core\Contracts\Serializer as SerializerContract;

/**
 * Class SingleFileStorage
 *
 * In opposite to FileStorage a SingleFileStorage serializes all data into one
 * single file.
 *
 * @package Ems\Core\Storages
 */
class SingleFileStorage implements Storage, Configurable, Arrayable
{
    use ConfigurableTrait;

    /**
     * @var Filesystem
     **/
    protected $filesystem;

    /**
     * @var SerializerContract
     **/
    protected $serializer;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var callable
     **/
    protected $checksumChecker;

    /**
     * @var ?Url
     **/
    protected $url;

    /**
     * @var bool
     */
    protected $loaded = false;

    /**
     * @var array
     **/
    protected $defaultOptions = [
        'checksum_method'   => 'crc32',
        'file_locking'      => true
    ];

    /**
     * @param Filesystem $filesystem
     * @param SerializerContract $serializer
     **/
    public function __construct(Filesystem $filesystem, SerializerContract $serializer)
    {
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
        $this->checksumChecker = function ($method, $data) {
            return $method == 'strlen' ? strlen($data) : hash($method, $data);
        };
    }

    /**
     * The file url
     *
     * @return ?Url
     **/
    public function getUrl() : ?Url
    {
        return $this->url;
    }

    /**
     * Set the file url
     *
     * @param ?Url $url
     *
     * @return self
     **/
    public function setUrl(?Url $url) : SingleFileStorage
    {
        $this->url = $url;
        $this->attributes = [];
        $this->loaded = false;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool (if successful)
     **/
    public function persist() : bool
    {
        if (!$this->url) {
            throw new LogicException("You have to set an url to SingleFileStorage to be able to save it.");
        }
        if ($this->loaded && !$this->attributes) {
            return $this->deleteFileIfExists($this->url);
        }

        $blob = $this->serializer->serialize($this->attributes);

        $handle = $this->filesystem->open($this->url, 'w');
        if ($this->getOption('file_locking')) {
            $handle->lock();
        }
        $result = (bool)$handle->write($blob);
        $handle->close();

        if (!$hashMethod = $this->getOption('checksum_method')) {
            return $result;
        }

        $checksum = $this->createChecksum($hashMethod, $blob);

        $savedBlob = $this->filesystem->open($this->url)->__toString();

        $this->checkData($hashMethod, $savedBlob, $checksum);

        return $result;
    }

    public function clear(array $keys = null)
    {
        $this->loadOnce();

        if ($keys === null) {
            $this->attributes = [];
            return;
        }

        if (!$keys) {
            return;
        }

        foreach ($keys as $key) {
            $this->offsetUnset($key);
        }

    }

    protected function deleteFileIfExists(Url $url) : bool
    {
        if ($this->filesystem->exists($url)) {
            return $this->filesystem->delete($url);
        }
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    public function storageType() : string
    {
        return self::FILESYSTEM;
    }

    /**
     * @inheritDoc
     */
    public function isBuffered() : bool
    {
        return true;
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
        $this->loadOnce();
        return isset($this->attributes[$offset]);
    }

    /**
     * Get value of $offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     **/
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $this->loadOnce();
        return $this->attributes[$offset];
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
        $this->loadOnce();
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset $offset.
     *
     * @param mixed $offset
     **/
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->loadOnce();
        unset($this->attributes[$offset]);
    }

    /**
     * Assign a custom callable to create the checksum. The checksum don't have
     * to be a paranoid secure hash. It is just to ensure data integrity and
     * should make cache attacks (a little) more difficult
     *
     * @param callable $checksumChecker
     *
     * @return self
     **/
    public function checkChecksumBy(callable $checksumChecker) : SingleFileStorage
    {
        $this->checksumChecker = $checksumChecker;
        return $this;
    }

    public function __toArray(): array
    {
        $this->loadOnce();
        return $this->attributes;
    }

    /**
     * Load the data from filesystem
     **/
    protected function loadOnce() : void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        if (!$this->filesystem->exists($this->url)) {
            return;
        }

        $blob = $this->filesystem->open($this->url)->__toString();

        $this->attributes = $this->serializer->deserialize($blob);

    }

    /**
     * Check the data integrity by the passed checksum. If invalid throw an
     * exception
     *
     * @param string $method
     * @param string $data
     * @param string $checksum
     *
     * @throws DataIntegrityException
     **/
    protected function checkData(string $method, string $data, string $checksum) : void
    {
        $freshChecksum = $this->createChecksum($method, $data);
        if ($freshChecksum != $checksum) {
            throw new DataIntegrityException('Checksum of file '.$this->getUrl()." failed. ($freshChecksum != $checksum)");
        }
    }

    /**
     * Create the data checksum
     *
     * @param string $method
     * @param string $data
     *
     * @return string
     **/
    protected function createChecksum(string $method, string $data) : string
    {
        return call_user_func($this->checksumChecker, $method, $data);
    }

}