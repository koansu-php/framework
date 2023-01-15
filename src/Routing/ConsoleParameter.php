<?php
/**
 *  * Created by mtils on 23.10.2022 at 17:38.
 **/

namespace Koansu\Routing;

use Koansu\Core\Contracts\Arrayable;
use ReflectionClass;
use ReflectionProperty as RP;
use JsonSerializable;

/**
 * Class ConsoleParameter
 *
 * This is the base class for console arguments and options
 *
 * @package Ems\Contracts\Routing
 */
abstract class ConsoleParameter implements Arrayable, JsonSerializable
{
    /**
     * The name. Just used internally to access the argument
     * @example $input->argument('name')
     * @example $input->option('name')
     *
     * @var string
     */
    public $name = '';

    /**
     * Is this parameter required?
     *
     * @var bool
     */
    public $required = false;

    /**
     * Type of this parameter. Can be bool|string|array
     *
     * @var string
     */
    public $type = 'bool';

    /**
     * The default value.
     *
     * @var mixed
     */
    public $default;

    /**
     * The description of the parameter.
     *
     * @var string
     */
    public $description = '';

    /**
     * @var string[]
     */
    protected static $propertyNames;

    public function __construct(array $properties=[])
    {
        if ($properties) {
            $this->fill($properties);
        }
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function fill(array $data) : ConsoleParameter
    {
        foreach (static::propertyNames() as $key) {

            if (isset($data[$key])) {
                $this->$key = $data[$key];
            }
        }
        if (!isset($data[$this->name]) && $this->type == 'bool') {
            $this->default = false;
        }
        return $this;
    }

    /**
     * @return array
     **/
    public function __toArray() : array
    {
        $array = [];
        foreach (static::propertyNames() as $key) {
            $array[$key] = $this->$key;
        }
        return $array;
    }

    /**
     * @return array
     */
    public function jsonSerialize() : array
    {
        return $this->__toArray();
    }

    /**
     *
     * @return string[]
     */
    protected static function propertyNames() : array
    {
        if (static::$propertyNames !== null) {
            return static::$propertyNames;
        }

        static::$propertyNames = [];

        $reflection = new ReflectionClass(static::class);
        foreach ($reflection->getProperties(RP::IS_PUBLIC) as $property) {
            if ($property->isDefault() && !$property->isStatic()) {
                static::$propertyNames[] = $property->getName();
            }
        }
        return static::$propertyNames;
    }
}