<?php
/**
 *  * Created by mtils on 15.01.2023 at 11:06.
 **/

namespace Koansu\Text\Exceptions;

use Exception;
use Koansu\Text\CharsetGuard;
use RuntimeException;

class InvalidCharsetException extends RuntimeException
{
    /**
     * @var string
     **/
    protected $failedString;

    /**
     * @var string
     **/
    protected $awaitedCharset;

    /**
     * @var CharsetGuard
     **/
    protected $guard;

    /**
     * @param string        $failedString
     * @param string        $awaitedCharset
     * @param Exception|null $previous (optional)
     **/
    public function __construct(string $failedString, string $awaitedCharset, Exception $previous=null)
    {
        parent::__construct("String is not in $awaitedCharset", 0, $previous);
        $this->awaitedCharset = $awaitedCharset;
        $this->failedString = $failedString;
    }

    /**
     * @return string
     **/
    public function failedString() : string
    {
        return $this->failedString;
    }

    /**
     * @return string
     **/
    public function awaitedCharset() : string
    {
        return $this->awaitedCharset;
    }

    /**
     * Try to guess the correct charset
     *
     * @return string
     **/
    public function suggestedCharset() : string
    {
        return $this->guard()->detect($this->failedString());
    }

    /**
     * @return string
     **/
    public function getHelp() : string
    {
        $awaited = $this->awaitedCharset();
        $suggested = $this->suggestedCharset();

        if (!$suggested) {
            return "String should be encoded in $awaited but has an undetectable charset.";
        }

        return "String should be encoded in $awaited but seems to be $suggested";

    }

    /**
     * Set the guard to determine the charset.
     *
     * @param CharsetGuard $guard
     *
     * @return $this
     **/
    public function useGuard(CharsetGuard $guard) : InvalidCharsetException
    {
        $this->guard = $guard;
        return $this;
    }

    /**
     * @return CharsetGuard
     **/
    protected function guard() : CharsetGuard
    {
        if (!$this->guard) {
            $this->guard = new CharsetGuard;
        }

        return $this->guard;
    }
}