<?php
/**
 *  * Created by mtils on 02.02.2022 at 21:11.
 **/

namespace Koansu\Core;

use TypeError;

use UnexpectedValueException;

use function gettype;
use function is_bool;
use function is_null;
use function is_numeric;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function preg_quote;
use function str_replace;

/**
 * This is a string object. In the future it will work in oo string syntax. For
 * now, it acts as a generic string to pass it through.
 */
class Str
{
    /**
     * @var string
     */
    protected $mimeType = 'text/plain';

    /**
     * @var mixed
     */
    protected $raw = '';

    /**
     * Create a new Str.
     *
     * @param string|int|float|bool|object $raw
     * @param string $mimeType
     */
    public function __construct($raw='', string $mimeType='text/plain')
    {
        $this->setRaw($raw);
        $this->mimeType = $mimeType;
    }

    /**
     * @return mixed
     */
    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * @param mixed $raw
     * @return Str
     */
    public function setRaw($raw): Str
    {
        if (is_string($raw) || is_numeric($raw) || is_bool($raw) || is_null($raw)) {
            $this->raw = $raw;
            return $this;
        }
        if (!is_object($raw)) {
            throw new TypeError('Unsupported parameter type "' . gettype($raw) . '"');
        }
        if (!method_exists($raw, '__toString')) {
            throw new UnexpectedValueException('The object for Str has to have a __toString method');
        }
        $this->raw = $raw;
        return $this;
    }

    /**
     * @return string
     */
    public function getMimeType() : string
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     * @return $this
     */
    public function setMimeType(string $mimeType) : Str
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (!is_object($this->raw)) {
            return (string)$this->raw;
        }
        return $this->raw->__toString();
    }

    /**
     * @param string $pattern
     * @param string $any
     * @param string $single
     * @return bool
     */
    public function isLike(string $pattern, string $any='%', string $single='_') : bool
    {
        return self::match($this->raw, $pattern, $any, $single);
    }

    /**
     * Match a string using wildcard (*) and single char (?) pattern.
     *
     * @param string $haystack
     * @param string $pattern
     * @param string $any
     * @param string $single
     * @return bool
     */
    public static function match(string $haystack, string $pattern, string $any='*', string $single='?') : bool
    {
        $anyEscape = '§§§§';
        $singleEscape = '§§§§§';

        // Save the special characters
        $pattern = str_replace([$single, $any], [$singleEscape, $anyEscape], $pattern);
        $regex = str_replace([$singleEscape, $anyEscape], ['.', '.*'], preg_quote($pattern));
        return preg_match("/^$regex$/i", $haystack);
    }

}