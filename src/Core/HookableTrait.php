<?php

namespace Koansu\Core;

use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\Core\Contracts\Hookable;
use Koansu\Core\Exceptions\HookNotFoundException;
use Koansu\Core\Exceptions\ImplementationException;

use function call_user_func;
use function get_class;
use function in_array;
use function is_array;
use function is_object;

/**
 * @see Hookable
 **/
trait HookableTrait
{
    /**
     * @var array
     **/
    protected $beforeListeners = [];

    /**
     * @var array
     **/
    protected $afterListeners = [];

    /**
     * {@inheritdoc}
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function onBefore($event, callable $listener)
    {
        $this->storeListener($event, $listener, $this->beforeListeners);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|object|string[]|object[]   $event
     * @param callable                          $listener
     *
     * @return void
     **/
    public function onAfter($event, callable $listener)
    {
        $this->storeListener($event, $listener, $this->afterListeners);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|object $event
     * @param string $position ('after'|'before'|'')
     *
     * @return array
     **/
    public function getListeners($event, string $position = '') : iterable
    {
        return $this->getAfterOrBeforeListeners($event, $position);
    }

    /**
     * @param string|object $event
     * @param string $position ('after'|'before'|'')
     *
     * @throws ImplementationException
     *
     * @return callable[]
     **/
    protected function getAfterOrBeforeListeners($event, string $position) : array
    {
        if (!in_array($position, ['before', 'after'])) {
            throw new ImplementationException("Unsupported event position '$position'");
        }

        $eventName = is_object($event) ? get_class($event) : $event;

        if ($position == 'before' && isset($this->beforeListeners[$eventName])) {
            return $this->beforeListeners[$eventName];
        }

        if ($position == 'after' && isset($this->afterListeners[$eventName])) {
            return $this->afterListeners[$eventName];
        }

        return [];
    }

    /**
     * Calls the assigned before listeners.
     *
     * @param string|object $event
     * @param array  $args
     *
     * @return bool
     **/
    protected function callBeforeListeners($event, array $args = []) : bool
    {
        return $this->callListeners($this->getListeners($event, 'before'), $args);
    }

    /**
     * Calls the assigned after listeners.
     *
     * @param string|object $event
     * @param array  $args
     *
     * @return bool
     **/
    protected function callAfterListeners($event, array $args = []) : bool
    {
        return $this->callListeners($this->getListeners($event, 'after'), $args);
    }

    /**
     * Calls the assigned after listeners.
     *
     * @param array $listeners
     * @param array  $args
     *
     * @return bool
     **/
    protected function callListeners(array $listeners, array $args = []) : bool
    {
        $called = false;
        foreach ($listeners as $listener) {
            call_user_func($listener, ...$args);
            $called = true;
        }

        return $called;
    }

    protected function storeListener($event, callable $listener, array &$listeners)
    {
        if (!is_array($event)) {
            $key = $this->confirmEvent($event);

            if (!isset($listeners[$key])) {
                $listeners[$key] = [];
            }
            $listeners[$key][] = $listener;
            return;
        }
        foreach ($event as $item) {
            $this->storeListener($item, $listener, $listeners);
        }

    }

    /**
     * Check if the event is supported. Throw an exception if this object
     * has method hooks
     *
     * @param string|object $event
     *
     * @return string
     *
     * @throws HookNotFoundException
     **/
    protected function confirmEvent($event) : string
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        if ($this instanceof HasMethodHooks && !in_array($eventName, $this->methodHooks())) {
            throw new HookNotFoundException("Event or method '$eventName' is not hookable");
        }
        return $eventName;
    }
}
