<?php
/**
 *  * Created by mtils on 04.02.2023 at 17:49.
 **/

namespace Koansu\Core;

use function get_class_methods;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * This trait is for classes whose methods are somehow exposed as features. In
 * a validator you may write validateEmail, validateLength, ... methods.
 */
trait MethodExposingTrait
{
    /**
     * @var array|null
     */
    protected $exposedMethods;

    protected abstract function getExposedMethodPrefix() : string;
    /**
     * Get the real name of the method which is callable by (snake cased) $name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getMethodByExposedName(string $name) : string
    {
        $methods = $this->getExposedMethods();
        return $methods[$name] ?? '';
    }

    /**
     * Get all snake case methods, indexed by its snake case name.
     *
     * @return string[]
     */
    protected function getExposedMethods() : array
    {
        if ($this->exposedMethods !== null) {
            return $this->exposedMethods;
        }

        $prefix = $this->getExposedMethodPrefix();

        foreach (get_class_methods($this) as $method) {

            if (!$this->isExposedMethod($method, $prefix)) {
                continue;
            }

            $this->exposedMethods[$this->methodToExposedName($method, $prefix)] = $method;
        }

        return $this->exposedMethods;
    }

    /**
     * Return true if the method is a snake case callable method.
     *
     * @param string $method
     * @param string $prefix
     *
     * @return bool
     */
    protected function isExposedMethod(string $method, string $prefix) : bool
    {
        if ($this->isIgnoredExposedMethod($method)) {
            return false;
        }
        return $method != $prefix && strpos($method, $prefix) === 0;
    }

    /**
     * Return true if the passed method is an exposed method.
     *
     * @param string $nativeName
     * @return bool
     */
    protected function isNativeMethodOfExposed(string $nativeName) : bool
    {
        foreach ($this->getExposedMethods() as $exposed=>$method) {
            if (strtolower($nativeName) == strtolower($method)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Overwrite this method to filter out some your methods.
     *
     * @param string $method
     * @return bool
     */
    protected function isIgnoredExposedMethod(string $method) : bool
    {
        return false;
    }

    /**
     * Converts a validation method name to a rule name
     *
     * @param string $methodName
     * @param string $prefix
     *
     * @return string
     **/
    protected function methodToExposedName(string $methodName, string $prefix) : string
    {
        return Type::snake_case(substr($methodName, strlen($prefix)));
    }
}