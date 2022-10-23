<?php
/**
 *  * Created by mtils on 09.10.2022 at 08:29.
 **/

namespace Koansu\Core;

use Iterator;
use Koansu\Core\Contracts\Connection;
use Koansu\Core\Exceptions\FailedToAcquireLockException;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\Exceptions\IOException;
use Koansu\Core\Exceptions\NotReadableException;
use Koansu\Core\Exceptions\NotWritableException;
use Koansu\Core\Exceptions\ResourceLockedException;
use Psr\Http\Message\StreamInterface;
use TypeError;

use function base64_encode;
use function fclose;
use function feof;
use function flock;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function function_exists;
use function fwrite;
use function get_resource_type;
use function is_int;
use function is_resource;
use function rewind;
use function str_replace;
use function stream_copy_to_stream;
use function stream_get_meta_data;
use function stream_is_local;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_supports_lock;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_SH;
use const LOCK_UN;
use const SEEK_SET;

/**
 * The stream is for everything you want to not load huge
 * data into memory.
 * Unlike psr-7 streams I prefer the iterator syntax to lazy load data. Seeking
 * in a data stream seems to be special to me, so I prefer to just iterate over
 * a stream.
 * The most methods are more to give an OO access to all the stream_* methods
 */
class Stream implements Connection, StreamInterface, Iterator
{
    // Constants for all meta data keys
    // @see https://www.php.net/manual/en/function.stream-get-meta-data.php

    /**
     * (bool)
     * @var string
     */
    const META_TIMED_OUT = 'timed_out';

    /**
     * (bool)
     * @var string
     */
    const META_BLOCKED = 'blocked';

    /**
     * (bool)
     * @var string
     */
    const META_AT_END = 'eof';

    /**
     * (int)
     * @var string
     */
    const META_UNREAD_BYTES = 'unread_bytes';

    /**
     * (string)
     * @var string
     */
    const META_STREAM_TYPE = 'stream_type';

    /**
     * (string)
     * @var string
     */
    const META_WRAPPER_TYPE = 'wrapper_type';

    /**
     * (mixed)
     * @var string
     */
    const META_WRAPPER_DATA = 'wrapper_data';

    /**
     * (bool)
     * @var string
     */
    const META_MODE = 'mode';

    /**
     * (bool)
     * @var string
     */
    const META_SEEKABLE = 'seekable';

    /**
     * (string)
     * @var string
     */
    const META_URI = 'uri';

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var string
     */
    protected $mode = 'r+';

    /**
     * @var bool
     */
    protected $isAsynchronous;

    /**
     * @var bool
     */
    protected $shouldLock = false;

    /**
     * @var int
     **/
    protected $chunkSize = 4096;

    /**
     * @var int
     */
    protected $timeout = -1;

    /**
     * @var string
     **/
    protected $currentValue;

    /**
     * @var int
     **/
    protected $position = 0;

    /**
     * @var bool
     */
    protected $lockApplied = false;

    /**
     * @var Url
     */
    protected $url;

    /**
     * @var string|Url|resource
     */
    protected $target;

    /**
     * Create a new stream. If you want to create a stream to access a
     * string wrap it by a Str() object. Strings are considered as URLs
     * and get automatically converted to them
     *
     * @param string|Str|Url|resource $target
     * @param string $mode
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct($target, string $mode='r+')
    {
        $this->mode = $mode;
        $this->target = $target;

        if ($target instanceof Url) {
            $this->url = $target;
            return;
        }
        if (is_resource($target)) {
            $this->resource = $target;
            return;
        }
        if ($target instanceof Str) {
            $this->url = new Url('data://text/plain;base64');
            return;
        }
        if (Type::isStringLike($target)) {
            $this->url = new Url((string)$target);
            return;
        }
        throw new TypeError('$target has to be resource,URL or string like.');
    }

    /**
     * Return the resource type. stream,ftp...
     *
     * @return string
     */
    public function type() : string
    {
        if (!is_resource($this->resource)) {
            return '';
        }
        return get_resource_type($this->resource);
    }

    /**
     * This is the read/write mode like r, r+, w, rw...
     *
     * @return string
     */
    public function mode() : string
    {
        if (!$this->hasValidResource()) {
            return $this->mode;
        }
        return $this->meta(static::META_MODE);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function isReadable() : bool
    {
        return static::isReadableMode($this->mode());
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function isWritable() : bool
    {
        return static::isWritableMode($this->mode());
    }

    /**
     * Return true if the stream is not blocking.
     * @see stream_set_blocking()
     *
     * @return bool
     */
    public function isAsynchronous() : bool
    {
        if (!$this->hasValidResource()) {
            return $this->isAsynchronous;
        }

        return !$this->meta(static::META_BLOCKED);
    }

    /**
     * Set the stream to be blocking. (Supported on sockets and files)
     * @see stream_set_blocking()
     *
     * @param bool $asynchronous
     */
    public function makeAsynchronous(bool $asynchronous = true) : void
    {
        $this->isAsynchronous = $asynchronous;
        if ($this->hasValidResource()) {
            $this->applyAsynchronous($this->resource);
        }
    }

    /**
     * Return true if the stream is locked.
     *
     * @return bool
     */
    public function isLocked() : bool
    {
        if (!$this->supportsLocking()) {
            return false;
        }
        return $this->pathIsLocked((string)$this->url());
    }

    /**
     * Return true if this stream supports locking.
     *
     * @see stream_supports_lock()
     *
     * @return bool
     */
    public function supportsLocking() : bool
    {
        if (!$this->hasValidResource()) {
            return false;
        }
        return stream_supports_lock($this->resource);
    }

    /**
     * Lock the stream, pass a mode or just a boolean value.
     *
     * @param bool|int $mode
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function lock($mode = true) : bool
    {
        if (!is_int($mode) || $mode === LOCK_UN) {
            $this->shouldLock = !($mode === LOCK_UN);
        }

        if (!$handle = $this->resource()) {
            return false;
        }

        return $this->applyLock($handle, is_int($mode) ? $mode : null);

    }

    /**
     * Remove the lock.
     *
     * @return bool
     */
    public function unlock() : bool
    {
        $this->shouldLock = false;

        if (!$handle = $this->resource()) {
            return false;
        }

        return $this->applyLock($handle);
    }

    /**
     * This is just for fluid syntax in filesystem:
     *
     * foreach($fs->open($path, 'r')->locked() as $chunk)
     *
     * @param bool|int $mode
     *
     * @return Stream
     * @noinspection PhpMissingParamTypeInspection
     */
    public function locked($mode = true) : Stream
    {
        if (!$this->lock($mode)) {
            throw new FailedToAcquireLockException();
        }
        return $this;
    }

    /**
     * Return true if the stream is local.
     *
     * @see stream_is_local()
     *
     * @return bool
     */
    public function isLocal() : bool
    {
        if($this->hasValidResource()) {
            return stream_is_local($this->resource);
        }
        $url = $this->url();
        if ($url->scheme == 'data') {
            return true;
        }
        return stream_is_local("$url");
    }

    /**
     * Return true if the stream is a tty.
     *
     * @return bool
     */
    public function isTerminalType() : bool
    {
        if(!$this->hasValidResource()) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty($this->resource);
        }

        return false;
    }

    /**
     * Set the (network) timeout.
     *
     * @param int $timeout
     */
    public function setTimeout(int $timeout) : void
    {
        $this->timeout = $timeout;
        if($this->hasValidResource() && $this->timeout !== -1) {
            $this->applyTimeout($this->resource);
        }
    }


    /**
     * Return the bytes which will be read in one iteration.
     *
     * @return int
     **/
    public function getChunkSize() : int
    {
        return $this->chunkSize;
    }

    /**
     * @see self::getChunkSize()
     *
     * @param int $chunkSize
     **/
    public function setChunkSize(int $chunkSize)
    {
        $this->chunkSize = $chunkSize;
    }

    /**
     * Reset the internal pointer to the beginning.
     **/
    public function rewind() : void
    {
        // Let's assume rewind is only called when reading
        $this->failOnWriteOnly();

        $this->onRewind();
        $this->initHandle();
        $this->position = 0;
        $this->currentValue = new None();

    }

    /**
     * @return string
     **/
    public function current() : string
    {
        if ($this->position === 0 && $this->currentValue instanceof None) {
            $this->currentValue = $this->readNext($this->resource(), $this->chunkSize);
        }
        return $this->currentValue;
    }

    /**
     * @return int
     **/
    public function key() : int
    {
        return $this->position;
    }

    public function next() : void
    {
        $this->currentValue = $this->readNext($this->resource, $this->chunkSize);
        $this->position = $this->currentValue === null ? -1 : $this->position + 1;
    }

    /**
     * This code leads to empty strings being valid for one iteration because
     * feof is not called. This is currently by design. I am not sure what the
     * right behaviour is. In general the iterator is valid because it is on
     * position 0 but current() returns an empty string.
     *
     * @return bool
     **/
    public function valid() : bool
    {
        return is_resource($this->resource) && $this->position !== -1;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function isSeekable() : bool
    {
        return (bool)$this->meta(static::META_SEEKABLE);
    }

    /**
     * {@inheritdoc}
     *
     * CAUTION: Using this method will mess up what key() returns.
     *
     * @param int $offset
     * @param int $whence (default: SEEK_SET)
     */
    public function seek($offset, $whence=SEEK_SET)
    {
        if (!$this->isSeekable()) {
            throw new ImplementationException(Type::of($this) . ' is not seekable.');
        }
        fseek($this->resource(), $offset, $whence);
        $this->next();
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $string
     *
     * @return int (For compatibility no int for written bytes)
     */
    public function write($string) : int
    {

        if (is_resource($string)) {
            return (bool)stream_copy_to_stream($string, $this->resource());
        }

        $isStream = $string instanceof Stream;

        if (!$this->isWritable()) {
            throw new NotWritableException('This stream is not writable');
        }

        if (!$isStream && Type::isStringable($string)) {
            return fwrite($this->resource(), $string);
        }

        if (!$isStream) {
            throw new TypeError('Unsupported data type in write(): ' . Type::of($string));
        }

        $dataResource = $string->resource();

        if (is_resource($dataResource)) {
            return $this->write($dataResource);
        }

        $resource = $this->resource();

        $bytes = 0;

        foreach ($string as $chunk) {
            $bytes += fwrite($resource, $chunk);
        }

        return $bytes;

    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function open() : void
    {
        $this->resource();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function close() : void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
        $this->assignResource(null);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function isOpen() : bool
    {
        return $this->hasValidResource();
    }

    /**
     * @return resource|object|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function resource()
    {
        if ($this->resource) {
            return $this->resource;
        }
        if (!$resource = $this->createResource($this->target)) {
            throw new IOException($this->url());
        }
        $this->assignResource($resource);
        return $this->resource;
    }

    /**
     * {@inheritDoc}
     *
     * @return Url
     */
    public function url() : Url
    {
        if ($this->url) {
            return $this->url;
        }
        if (!$uri = $this->meta(static::META_URI)) {
            $this->url = new Url();
            return $this->url;
        }
        $this->url = new Url($uri);
        return $this->url;
    }

    /**
     * Return meta data of a stream.
     *
     * @param ?string $key (optional)
     *
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     *
     * @see stream_get_meta_data()
     */
    public function meta(string $key = null)
    {
        if (!$this->resource) {
            return null;
        }

        $meta = stream_get_meta_data($this->resource);

        if (!$key) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    /**
     * Read a few bytes from the stream.
     *
     * @param int $length
     *
     * @return string
     */
    public function read($length) : string
    {
        return $this->readFromHandle($this->resource(), $length);
    }


    /**
     * Renders this object. Without any exception problems you can render
     * the content here.
     *
     * @return string
     **/
    public function __toString() : string
    {
        if ($this->target instanceof Str && !$this->target instanceof Url) {
            return $this->target->__toString();
        }
        $this->failOnWriteOnly();

        if ($this->shouldLock === false) {
            return $this->readAll();

        }

        $path = (string)$this->url();

        // If the file was not locked by me (but someone else)
        if (!$this->lockApplied && $this->pathIsLocked($path)) {
            throw new ResourceLockedException("$path is locked");
        }

        return $this->readAll();

    }

    /**
     * @return ?int
     */
    public function getSize() : ?int
    {
        if (!$resource = $this->resource()) {
            return null;
        }
        if (!$stat = fstat($resource)) {
            return null;
        }
        return $stat['size'] ?? null;
    }

    /**
     * @param $key
     * @return mixed|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getMetadata($key = null)
    {
        return $this->meta($key);
    }

    /**
     * @return int
     */
    public function tell() : int
    {
        return $this->key();
    }

    /**
     * @return bool
     */
    public function eof() : bool
    {
        return !$this->valid();
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        $resource = $this->resource;
        $this->close();
        return is_resource($resource) ? $resource : null;
    }

    /**
     * @return string
     */
    public function getContents() : string
    {
        return $this->readAll();
    }

    /**
     * Close the connection when destructing
     *
     * @return void
     * @link https://php.net/manual/en/language.oop5.decon.php
     */
    public function __destruct()
    {
        // Do not close passed resources
        if (!is_resource($this->target)) {
            $this->close();
        }
    }

    public function forUrl($url, $mode='r+') : Stream
    {
        return new static($url instanceof Url ? $url : new Url($url), $mode);
    }

    public function forString(string $string) : Stream
    {
        $resource = fopen('data://text/plain;base64,'.base64_encode($string), 'r+');
        return static::forResource($resource);
    }

    public function forResource($resource, Url $url=null) : Stream
    {
        $stream = new static($resource);
        if ($url) {
            $stream->url = $url;
        }
        return $stream;
    }

    /**
     * Hook into the rewind call.
     **/
    protected function onRewind()
    {
    }

    /**
     * Read the next chunk and return it.
     *
     * @param resource $handle
     * @param int      $chunkSize
     *
     * @return string|null
     **/
    protected function readNext($handle, int $chunkSize) : ?string
    {

        if (feof($handle)) {
            return null;
        }

        return $this->readFromHandle($handle, $chunkSize);
    }

    /**
     * @param resource $handle
     * @param int      $chunkSize
     *
     * @return bool|string
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function readFromHandle($handle, int $chunkSize)
    {
        return fread($handle, $chunkSize);
    }

    /**
     * Re-implement this method to allow fast toString/complete reading.
     *
     * @return string
     */
    protected function readAll() : string
    {
        $string = '';
        foreach ($this as $chunk) {
            $string .= $chunk;
        }
        $this->rewind();
        return $string;
    }

    /**
     * Create or rewind the handle.
     *
     * @return resource
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function initHandle()
    {
        if ($this->isOpen()) {
            rewind($this->resource);
            return $this->resource;
        }
        return $this->resource();
    }

    /**
     * @return bool
     */
    protected function hasValidResource() : bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }
        return get_resource_type($this->resource) != 'Unknown';
    }

    /**
     * @return int
     */
    protected function getLockMode() : int
    {
        if ($this->isWritable()) {
            return LOCK_EX;
        }
        return LOCK_SH;
    }

    /**
     * @param resource|null $resource
     */
    protected function assignResource($resource)
    {
        $this->resource = $resource;
    }

    /**
     * @param string|url|resource $target
     *
     * @return resource|false
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function createResource($target)
    {
        if (is_resource($target)) {
            return $target;
        }
        if ($target instanceof Url) {
            return $this->openUrlHandle($target);
        }
        if ($target instanceof Str) {
            return $this->openStringHandle($target->getRaw());
        }
        return $this->openUrlHandle($this->url());
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function openUrlHandle(string $url)
    {
        return @fopen($url, $this->mode);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function openStringHandle(string $string)
    {
        return fopen('data://text/plain;base64,'.base64_encode($string), 'r+');
    }

    /**
     * @param resource $resource
     *
     * @return bool
     */
    protected function applyAsynchronous($resource) : bool
    {
        return stream_set_blocking($resource, !$this->isAsynchronous);
    }

    /**
     * @param resource $resource
     * @param ?int     $mode (optional)
     *
     * @return bool
     */
    protected function applyLock($resource, int $mode=null) : bool
    {
        if ($this->shouldLock === false && $mode === null) {
            $this->lockApplied = false;
            return flock($resource, LOCK_UN);
        }

        if ($mode === null) {
            $mode = $this->shouldLock === true ? $this->getLockMode() : $this->shouldLock;
        }

        $wouldBlock = null;

        $result = flock($resource, $mode, $wouldBlock);

        // If we are blocking a false return value means failed to lock
        // A successful return value of flock() always means success
        if (!$this->isNonBlockingMode($mode) || $result) {
            $this->lockApplied = $result;
            return $result;
        }

        // In non-blocking mode flock will return false if another process is
        // holding the lock too.
        // $wouldBlock tells us that the lock did fail because it would have
        // blocked another process that acquired the lock previously
        if ($wouldBlock) {
            return false;
        }

        // So that should be the only valid reason not to getting the lock
        // (Would be the case if another process blocks exclusively??)
        throw new ResourceLockedException('Failed to acquire a non blocking lock');
    }

    /**
     * @param resource $resource
     *
     * @return bool
     */
    protected function applyTimeout($resource) : bool
    {
        return stream_set_timeout($resource, $this->timeout);
    }

    /**
     * @param resource $resource
     */
    protected function applySettings($resource)
    {

        if ($this->isAsynchronous !== null) {
            $this->applyAsynchronous($resource);
        }


        if ($this->shouldLock !== false) {
            $this->applyLock($resource);
        }

        if ($this->timeout !== -1) {
            $this->applyTimeout($resource);
        }
    }

    /**
     * @param $path
     * @param ?int $lockMode
     * @param string $openMode
     *
     * @return bool
     */
    protected function pathIsLocked($path, int $lockMode = null, string $openMode = 'r+') : bool
    {
        $lockTestResource = fopen($path, $openMode);
        $lockMode = $lockMode ?: $this->getLockMode() | LOCK_NB;

        if (!flock($lockTestResource, $lockMode)) {
            return true;
        }

        // Remove my created lock.
        flock($lockTestResource, LOCK_UN);

        return false;

    }

    /**
     * @param string $mode
     *
     * @return bool
     */
    public static function isReadableMode(string $mode) : bool
    {
        return Str::stringContains(static::flagLess($mode), ['r', '+']);
    }

    /**
     * @param string $mode
     *
     * @return bool
     */
    public static function isWritableMode(string $mode) : bool
    {
        return Str::stringContains(static::flagLess($mode), ['+', 'w', 'a', 'x', 'c']);
    }

    /**
     * @param string $mode
     *
     * @return bool
     */
    public static function isAppendingMode(string $mode) : bool
    {
        return Str::stringContains(static::flagLess($mode), 'a');
    }

    /**
     * @param string $mode
     *
     * @return string
     */
    protected static function flagLess(string $mode) : string
    {
        // replace 'e', the close-on-exec Flag
        return str_replace(['e', 'b'], '', $mode);
    }

    protected function failOnWriteOnly()
    {
        if (!$this->isReadable()) {
            throw new NotReadableException('Cannot read from a write only stream');
        }
    }

    /**
     * Return if the lock mode is blocking.
     *
     * @param int $lockMode
     *
     * @return bool
     */
    protected function isNonBlockingMode(int $lockMode) : bool
    {
        return $lockMode === (LOCK_EX | LOCK_NB) || $lockMode === (LOCK_SH | LOCK_NB);
    }
}