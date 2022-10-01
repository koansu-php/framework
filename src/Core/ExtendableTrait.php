<?php

namespace Koansu\Core;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\Exceptions\HandlerNotFoundException;

use function array_keys;
use function call_user_func;

/**
 * This trait makes your class implement the Extendable interface.
 *
 * @see Extendable
 **/
trait ExtendableTrait
{
    /**
     * Here the callables are held.
     *
     * @var array<string, callable>
     **/
    protected $_extensions = [];

    /**
     * @var string[]
     */
    protected $_closestRegistered = [];

    /**
     * Extend the object with an $extension under $name.
     *
     * @param string   $name
     * @param callable $callable
     *
     * @return void
     **/
    public function extend(string $name, callable $callable)
    {
        $this->_extensions[$name] = $callable;
        $this->_closestRegistered = [];
    }

    /**
     * Return the extension named $name.
     *
     * @param string $name
     *
     * @return callable
     *
     * @throws HandlerNotFoundException If extension not found
     **/
    public function getExtension(string $name) : callable
    {
        if ($this->hasExtension($name)) {
            return $this->_extensions[$name];
        }
        throw new HandlerNotFoundException(get_class($this).": No extension named \"$name\" found");
    }

    /**
     * Return the names of all extensions.
     *
     * @return string[]
     **/
    public function getExtensionNames() : array
    {
        return array_keys($this->_extensions);
    }

    /**
     * Remove a previously added extension.
     *
     * @param string $name
     *
     * @return void
     *
     * @throws HandlerNotFoundException If extension was not found
     */
    public function removeExtension(string $name)
    {
        $this->getExtension($name);
        unset($this->_extensions[$name]);
        if (isset($this->_closestRegistered[$name])) {
            unset($this->_closestRegistered[$name]);
        }
    }

    /**
     * Return if an extension with name $name exists.
     *
     * @param string $name
     *
     * @return bool
     **/
    protected function hasExtension(string $name) : bool
    {
        return isset($this->_extensions[$name]);
    }

    /**
     * Call all extensions until one returns not null and return the result. Use
     * this to implement a chain of responsibility.
     *
     * @param callable[] $extensions With $name as keys(!)
     * @param mixed $args (optional)
     * @param bool $fail
     *
     * @return mixed
     */
    protected function callUntilNotNull(array $extensions, $args=[], bool $fail=false)
    {
        if ($fail && !$extensions) {
            throw new HandlerNotFoundException('No extensions passed (or found) to fulfill the call.', HandlerNotFoundException::NO_HANDLERS_FOUND);
        }
        foreach ($extensions as $name=>$extension) {
            $result = $this->callExtension($name, $extension, $args);

            if ($result !== null) {
                return $result;
            }
        }

        if ($fail) {
            throw new HandlerNotFoundException('Extensions found but none wanted to answer the request.', HandlerNotFoundException::NO_HANDLER_ANSWERED);
        }

        return null;
    }

    /**
     * Call the extension named $name with $params. Overwrite this method for
     * custom call behaviour.
     *
     * @param string    $name
     * @param callable  $extension
     * @param mixed $params (optional)
     *
     * @return mixed
     *
     * @noinspection PhpUnusedParameterInspection
     */
    protected function callExtension(string $name, callable $extension, $params = [])
    {
        return call_user_func($extension, ...(array)$params);
    }

    /**
     * @return callable[]
     */
    protected function allExtensions() : array
    {
        return $this->_extensions;
    }

    /**
     * This method is for collecting extensions WHICH NAMES are patterns.
     *
     * @param string $name
     *
     * @return array
     **/
    protected function extensionsWhoseNameMatches(string $name) : array
    {

        $extensions = [];

        foreach ($this->allExtensions() as $pattern=>$extension) {
            if ($this->patternMatches($pattern, $name)) {
                $extensions[] = $extension;
            }
        }

        return $extensions;

    }

    /**
     * Find the extension that was registered for a parent class of $class.
     *
     * So in $host->extend(DataObject::class, $handler) and a class hierarchy like this:
     * User -> DataObject -> Entity
     * A handler extended vor DataObject would be returned in favour to one
     * extended vor Entity. A handler extended for User would be prio 1.
     *
     * @param string $class
     * @param bool $fail
     * @return callable|null
     */
    protected function closestExtensionForClass(string $class, bool $fail=false) : ?callable
    {
        if (isset($this->_closestRegistered[$class])) {
            return $this->_extensions[$this->_closestRegistered[$class]];
        }
        $classes = array_keys($this->_extensions);

        if ($closest = Type::closest($classes, $class)) {
            $this->_closestRegistered[$class] = $closest;
            return $this->_extensions[$closest];
        }

        if ($fail) {
            throw new HandlerNotFoundException("No handlers were registered to match class $class", HandlerNotFoundException::NO_HANDLERS_FOUND);
        }

        return null;

    }

    /**
     * Match a string on a pattern.
     *
     * @param string $pattern
     * @param string $string
     *
     * @return bool
     **/
    protected function patternMatches(string $pattern, string $string) : bool
    {
        return fnmatch($pattern, $string, FNM_NOESCAPE);
    }
}
