<?php
/**
 *  * Created by mtils on 28.10.2022 at 11:51.
 **/

namespace Koansu\Core\Serializers;

use Koansu\Core\Contracts\Serializer;

use function in_array;
use function json_decode;
use function json_encode;

use const JSON_BIGINT_AS_STRING;
use const JSON_NUMERIC_CHECK;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class JsonSerializer implements Serializer
{

    /**
     * @var int
     **/
    const NUMERIC_CHECK = JSON_NUMERIC_CHECK;

    /**
     * @var int
     **/
    const PRETTY = JSON_PRETTY_PRINT;

    /**
     * @var int
     **/
    const UNESCAPED_SLASHES = JSON_UNESCAPED_SLASHES;

    /**
     * @var int
     **/
    const UNESCAPED_UNICODE = JSON_UNESCAPED_UNICODE;

    /**
     * @var int
     **/
    const PRESERVE_ZERO_FRACTION = JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @var int
     **/
    const BIGINT_AS_STRING = JSON_BIGINT_AS_STRING;

    /**
     * @var string
     **/
    const DEPTH = 'depth';

    /**
     * @var string
     **/
    const AS_ARRAY = 'array';

    /**
     * @var bool
     */
    protected $defaultAsArray = true;

    /**
     * @var bool
     */
    protected $defaultPrettyPrint = false;

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    public function mimeType() : string
    {
        return 'application/json';
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $value
     * @param array $options (optional)
     *
     * @return string
     **/
    public function serialize($value, array $options=[]) : string
    {
        return json_encode(
            $value,
            $this->bitmask($options),
            $this->getDepth($options)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param string $string
     * @param array $options (optional)
     *
     * @return array|object
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deserialize(string $string, array $options=[])
    {
        return json_decode(
            $string,
            $this->shouldDecodeAsArray($options),
            $this->getDepth($options),
            $this->bitmask($options, true)
        );
    }

    /**
     * Configure if it should return array by default in deserialize.
     *
     * @param bool $asArray
     *
     * @return $this
     */
    public function asArrayByDefault(bool $asArray=true) : JsonSerializer
    {
        $this->defaultAsArray = $asArray;
        return $this;
    }

    /**
     * Configure if it should return array by default in deserialize.
     *
     * @param bool $pretty
     *
     * @return $this
     */
    public function prettyByDefault(bool $pretty=true) : JsonSerializer
    {
        $this->defaultPrettyPrint = $pretty;
        return $this;
    }

    /**
     * Build a bitmask out of $options to use them with json_*
     *
     * @param array $options
     * @param bool  $forDeserialize (default: false)
     *
     * @return int
     **/
    protected function bitmask(array $options, bool $forDeserialize=false) : int
    {
        $bitmask = 0;

        $prettyPrintWasSet = false;

        foreach ($options as $key=>$value) {

            if (in_array($key, [static::DEPTH, static::AS_ARRAY])) {
                continue;
            }

            if ($key == static::PRETTY) {
                $prettyPrintWasSet = true;
            }
            if ($value) {
                $bitmask = $bitmask | $key;
            }

        }

        if (!$forDeserialize && !$prettyPrintWasSet && $this->defaultPrettyPrint) {
            $bitmask  = $bitmask | static::PRETTY;
        }

        return $bitmask;

    }

    /**
     * @param array $options
     *
     * @return int
     **/
    protected function getDepth(array $options) : int
    {
        if (isset($options[static::DEPTH])) {
            return $options[static::DEPTH];
        }
        return 512;
    }

    /**
     * @param array $options
     *
     * @return bool
     **/
    protected function shouldDecodeAsArray(array $options) : bool
    {
        if (isset($options[static::AS_ARRAY])) {
            return $options[static::AS_ARRAY];
        }
        return $this->defaultAsArray;
    }

}
