<?php

namespace Koansu\Core\Contracts;

use Koansu\Core\Exceptions\HandlerNotFoundException;

/**
 * The Extendable interface is for objects which supports __call() methods
 * or other methods to extend its features.
 * The name is added to not lose control over what you added to the using
 * classes.
 * The name can also be a pattern to match events or routes.
 * Or you can extend by class name. (extend(User:class, $userHandler)) and match
 * the extensions by class hierarchy.
 **/
interface Extendable
{
    /**
     * Extend the object with an $extension under $name.
     *
     * @param string   $name
     * @param callable $extension
     *
     * @return void
     **/
    public function extend(string $name, callable $extension);

    /**
     * Return the extension stored under $name.
     *
     * @param string $name
     *
     * @throws HandlerNotFoundException If extension not found
     *
     * @return callable
     **/
    public function getExtension(string $name) : callable;

    /**
     * Return the names of all extensions.
     *
     * @return string[]
     **/
    public function getExtensionNames() : array;

    /**
     * Remove a previously added extension.
     *
     * @param string $name
     *
     * @return void
     *
     * @throws HandlerNotFoundException If extension was not found
     */
    public function removeExtension(string $name);
}
