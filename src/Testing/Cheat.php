<?php
/**
 *  * Created by mtils on 08.10.2022 at 08:27.
 **/

namespace Koansu\Testing;

use ReflectionException;
use ReflectionProperty;
use ReflectionMethod;

/**
 * This class allows to get protected and private values from classes
 * and allows to call private and protected methods.
 * So you can test your classes without adding artificial public methods
 * just to test them.
 **/
class Cheat
{
    /**
     * Get the value of a property, even if it is protected or private.
     *
     * @param object $object
     * @param string $property
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     * @throws ReflectionException
     */
    public static function get(object $object, string $property)
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    /**
     * Set the value of a property, even if it is protected or private.
     *
     * @param object $object
     * @param string $property
     * @param mixed $value
     *
     * @return void
     *
     * @throws ReflectionException
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function set(object $object, string $property, $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    /**
     * Call a method of an object, even if it is protected or private.
     *
     * @param object $object
     * @param string $method
     * @param array $args (optional)
     *
     * @return mixed
     *
     * @throws ReflectionException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function call(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

}