<?php
/**
 *  * Created by mtils on 27.10.2022 at 17:21.
 **/

namespace Koansu\Core\Contracts;

interface Serializer
{
    /**
     * Return a mimetype for the serialized data
     *
     * @return string
     **/
    public function mimeType() : string;

    /**
     * Serializer arbitrary data into a string. Throw an exception if
     * you cant serialize the data. (gettype(x) == 'resource', objects, ..)
     *
     * @param mixed $value
     * @param array $options (optional)
     *
     * @return string
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function serialize($value, array $options=[]) : string;

    /**
     * Deserializer arbitrary data from a string. Throw an exception
     * if you cannot deserialize the data.
     *
     * @param string $string
     * @param array $options (optional)
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deserialize(string $string, array $options=[]);
}