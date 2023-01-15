<?php
/**
 *  * Created by mtils on 28.10.2022 at 09:53.
 **/

namespace Koansu\Core;

use Koansu\Core\Contracts\Serializer as SerializerContract;
use Koansu\Core\Contracts\Extendable;

use Koansu\Core\Exceptions\DataIntegrityException;

use LogicException;
use TypeError;

use function call_user_func;
use function error_get_last;
use function is_resource;
use function serialize;
use function strpos;
use function unserialize;
use function version_compare;

use const PHP_VERSION;

/**
 * This is "THE SERIALIZER". Its normal usage is just to replace serialize()
 * and unserialize().
 * But you can also use it as a factory for other formats. Use the extendable
 * interface for it and call self::forMimeType($mime)->deserialize().
 */
class Serializer implements SerializerContract, Extendable
{
    use ExtendableTrait;

    /**
     * @var bool
     **/
    protected $useOptions = false;

    /**
     * @var callable
     */
    protected $errorGetter;

    /**
     * @var string
     **/
    protected $serializeFalseAs = '--|false-serialized|--';

    public function __construct(callable $errorGetter=null)
    {
        $this->useOptions = (version_compare(PHP_VERSION, '7.0.0') >= 0);
        $this->errorGetter = $errorGetter ?: function () { return error_get_last(); };
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    public function mimeType() : string
    {
        return 'application/vnd.php.serialized';
    }

    /**
     * {@inheritdoc}
     * This serializer cant handle false values because php returns false when
     * trying to deserialize malformed serialized data.
     *
     * @param mixed $value
     * @param array $options (optional)
     *
     * @return string
     **/
    public function serialize($value, array $options=[]) : string
    {
        if (is_resource($value)) {
            throw new TypeError('You cant serialize a resource');
        }

        if ($value === $this->serializeFalseAs) {
            throw new LogicException('You cant serialize '.$this->serializeFalseAs.' cause its internally used to encode false');
        }

        if ($value === false) {
            $value = $this->serializeFalseAs;
        }

        return serialize($value);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $string
     * @param array $options (optional)
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deserialize(string $string, array $options=[])
    {
        $value = $this->useOptions? @unserialize($string, $options) : @unserialize($string);

        if ($value !== false) {
            return $value === $this->serializeFalseAs ? false : $value;
        }

        // This does not work on hhvm
        if ($error = $this->unserializeError()) {
            throw new DataIntegrityException('Malformed serialized data: '.$error);
        }

        throw new DataIntegrityException('Unable to deserialize data');
    }

    /**
     * Return a serializer for $mimetype.
     *
     * @param string $mimetype
     * @return SerializerContract
     */
    public function forMimeType(string $mimetype) : SerializerContract
    {
        return call_user_func($this->getExtension($mimetype));
    }

    /**
     * Return the error that did occur when unserialize was called
     *
     * @return string|bool
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function unserializeError()
    {
        if (!$error = call_user_func($this->errorGetter)) {
            return false;
        }

        if ($error['file'] != __FILE__) {
            return false;
        }

        /** @noinspection PhpStrFunctionsInspection */
        if (strpos($error['message'], 'unserialize(') !== 0) {
            return false;
        }

        return $error['message'];
    }
}
