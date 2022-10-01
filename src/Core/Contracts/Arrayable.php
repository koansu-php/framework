<?php


namespace Koansu\Core\Contracts;

/**
 * Turn an object (fast) into an array. Only root should
 * be an array, so you do not need to test for a __toArray() method of children
 * while building the array.
 * The syntax is inspired by the __toString() method and this rfc:
 * @see https://wiki.php.net/rfc/object_cast_to_types
 **/
interface Arrayable
{
    /**
     * This is a performance related method. In this method
     * you should implement the fastest way to get every
     * key and value as an array.
     * Only the root has to be an array, it should not build
     * the array by traversing the values.
     *
     * @return array
     **/
    public function __toArray() : array;
}
