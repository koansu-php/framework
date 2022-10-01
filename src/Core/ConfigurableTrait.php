<?php

namespace Koansu\Core;

use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\Exceptions\UnsupportedOptionException;

use function array_key_exists;
use function is_array;

/**
 * This Trait if for easy implementation of Configurable.
 *
 * @see Configurable
 *
 * You just have to add an array of $defaultOptions
 * to your class
 **/
trait ConfigurableTrait
{
    /**
     * @var array
     **/
    protected $_options = [];

    /**
     * Get the option $key
     *
     * @param string $key
     *
     * @throws UnsupportedOptionException
     *
     * @return mixed
     **/
    public function getOption(string $key)
    {
        if (array_key_exists($this->confirmOption($key), $this->_options)) {
            return $this->_options[$key];
        }
        return $this->getDefaultOptions()[$key];
    }

    /**
     * Set the option $key
     *
     * @param string|array $key
     * @param mixed  $value
     *
     * @return Configurable
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @throws UnsupportedOptionException
     */
    public function setOption($key, $value=null) : Configurable
    {
        if (!is_array($key)) {
            $this->_options[$this->confirmOption($key)] = $value;
            return $this;
        }
        foreach ($key as $option=>$value) {
            $this->setOption($option, $value);
        }
        return $this;
    }

    /**
     * Return all existing option keys.
     *
     * @return array
     **/
    public function supportedOptions() : array
    {
        return array_keys($this->getDefaultOptions());
    }

    /**
     * Reset all passed options to its defaults.
     *
     * @param string[] $key,...
     *
     * @return self
     *
     * @noinspection PhpDocSignatureInspection
     * @throws UnsupportedOptionException
     */
    public function resetOptions(string ...$key) : Configurable
    {
        if (!$key) {
            $this->_options = [];
            return $this;
        }
        foreach ($key as $singleKey) {
            if (isset($this->_options[$this->confirmOption($singleKey)])) {
                unset($this->_options[$singleKey]);
            }
        }

        return $this;
    }

    /**
     * This is a helper method for classes which support manual
     * passed options to overwrite internal options.
     *
     * @param string $key
     * @param array $passedOptions
     *
     * @return mixed
     *
     * @throws UnsupportedOptionException
     */
    protected function mergeOption(string $key, array $passedOptions)
    {
        return $passedOptions[$key] ?? $this->getOption($key);
    }

    /**
     * This is a helper method for classes which support manual
     * passed options to overwrite internal options.
     *
     * @param array $passedOptions
     *
     * @return array
     *
     * @throws UnsupportedOptionException
     */
    protected function mergeOptions(array $passedOptions) : array
    {
        $merged = [];
        foreach ($this->supportedOptions() as $option) {
            $merged[$option] = $this->mergeOption($option, $passedOptions);
        }
        return $merged;
    }

    /**
     * Check if $key is supported, otherwise throw an Exception.
     *
     * @param string $key
     *
     * @return string
     **/
    protected function confirmOption(string $key) : string
    {
        if (!$this->optionExists($key)) {
            throw new UnsupportedOptionException("Option '$key' is not supported");
        }
        return $key;
    }

    /**
     * @param string $key
     *
     * @return bool
     **/
    protected function optionExists(string $key) : bool
    {
        $defaultOptions = $this->getDefaultOptions();

        return isset($defaultOptions[$key]);
    }

    /**
     * Get the default option. Throw an exception if the class
     * which uses this trait has implemented a $defaultOptions
     * property.
     *
     * @return array
     **/
    protected function getDefaultOptions() : array
    {
        if (!isset($this->defaultOptions)) {
            throw new ImplementationException(get_class($this).' has to define a property named $defaultOptions');
        }
        return $this->defaultOptions;
    }
}
