<?php


namespace Koansu\Core\Contracts;


/**
 * This interface is for all objects that support a custom object
 * factory.
 * So you can assign your own callable to create objects. The class
 * or interface name will by the first argument to the assigned callable.
 * The second are parameters, but you should avoid using parameters.
 * The Koansu\DependencyInjection\Container is by default callable so you can
 * directly use it.
 * Or just a Closure forward:
 * @example $class->createObjectsBy(function ($class) {
 *     return $container->create($class);
 * });
 *
 **/
interface SupportsCustomFactory
{
    /**
     * Assign a factory to create objects.
     *
     * @param callable $factory
     *
     * @return void
     **/
    public function createObjectsBy(callable $factory);
}
