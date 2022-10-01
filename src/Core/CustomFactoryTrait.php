<?php
/**
 *  * Created by mtils on 04.09.2022 at 21:33.
 **/

namespace Koansu\Core;

use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Core\Exceptions\SymbolNotFoundException;
use ReflectionClass;
use ReflectionException;

use function call_user_func;

/**
 * Using this trait makes your class implementing SupportsCustomFactory.
 * Create objects by createObject()
 *
 * @see SupportsCustomFactory
 */
trait CustomFactoryTrait
{
    /**
     * @var ?callable
     */
    protected $_customFactory;

    /**
     * Assign a factory to create objects.
     *
     * @param callable $factory
     *
     * @return void
     **/
    public function createObjectsBy(callable $factory)
    {
        $this->_customFactory = $factory;
    }

    /**
     * Create the object by factory (or reflection if no factory assigned)
     *
     * @param string $abstract
     * @param array $parameters (optional)
     *
     * @return object
     */
    protected function createObject(string $abstract, array $parameters=[]) : object
    {
        if ($this->_customFactory) {
            return call_user_func($this->_customFactory,  ...[$abstract, $parameters]);
        }
        try {
            return $this->createWithoutFactory($abstract, $parameters);
        } catch (ReflectionException $e) {
            throw new SymbolNotFoundException("Class $abstract cant be creates", SymbolNotFoundException::CLASS_NOT_FOUND, $e);
        }

    }

    /**
     * Create the object by yourself.
     *
     * @param string $abstract (class or interface)
     * @param array  $parameters (optional)
     *
     * @return object
     *
     * @throws ReflectionException
     **/
    protected function createWithoutFactory(string $abstract, array $parameters=[]) : object
    {
        return (new ReflectionClass($abstract))->newInstanceArgs($parameters);
    }
}