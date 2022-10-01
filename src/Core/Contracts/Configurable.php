<?php

namespace Koansu\Core\Contracts;

use Koansu\Core\Exceptions\UnsupportedOptionException;

/**
 * This is a basic interface for configurable
 * objects. A compiler, parser or something like
 * that are typical use cases.
 **/
interface Configurable
{
    /**
     * Get the value for option $key. You have to throw an Unsupported
     * if the key is not none.
     *
     * @param string $key
     *
     * @throws UnsupportedOptionException
     *
     * @return mixed
     **/
    public function getOption(string $key);

    /**
     * Set the option $key to $value. Throw an UnsupportedOptionException if the
     * key is not none. Pass an array to set multiple values at once.
     *
     * @param string|array $key
     * @param mixed        $value (optional)
     *
     * @throws UnsupportedOptionException
     *
     * @return self
     **/
    public function setOption($key, $value=null) : Configurable;

    /**
     * Return an array of supported option keys.
     *
     * @return string[]
     **/
    public function supportedOptions() : array;

    /**
     * Reset option(s) to its default value(s).  Pass no
     * key to reset all options, pass a string for one option
     * and multiple keys for many options. If any unknown keys are
     * passed throw an UnsupportedOptionException.
     *
     * @param string[] $key,...
     *
     * @throws UnsupportedOptionException
     *
     * @return self
     **/
    public function resetOptions(string ...$key) : Configurable;
}
