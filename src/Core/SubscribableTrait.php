<?php
/**
 *  * Created by mtils on 01.10.2022 at 09:46.
 **/

namespace Koansu\Core;

use Koansu\Core\Contracts\Subscribable;
use Koansu\Core\Exceptions\ImplementationException;

use function call_user_func;
use function get_class;
use function is_object;

/**
 * @see Subscribable
 */
trait SubscribableTrait
{
    /**
     * @var array
     **/
    protected $listeners = [];

    /**
     * {@inheritdoc}
     *
     * @param string|object|string[]|object[]   $event
     * @param callable                          $listener
     **/
    public function on($event, callable $listener)
    {
        if (!is_array($event)) {
            $eventName = $this->confirmEvent($event);

            if (!isset($this->listeners[$eventName])) {
                $this->listeners[$eventName] = [];
            }
            $this->listeners[$eventName][] = $listener;
            return;
        }
        foreach ($event as $singleEvent) {
            $this->on($singleEvent, $listener);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param string|object $event
     * @param string        $position ('after'|'before'|'')
     *
     * @throws ImplementationException
     *
     * @return callable[]
     **/
    public function getListeners($event, string $position = '') : iterable
    {
        if ($position !== '') {
            throw new ImplementationException('SubscribableTrait only supports an empty position');
        }
        return $this->getOnListeners($event);
    }

    /**
     * @param string $event
     *
     * @return callable[]
     **/
    protected function getOnListeners(string $event) : array
    {
        if (isset($this->listeners[$event])) {
            return $this->listeners[$event];
        }

        return [];
    }

    /**
     * Calls the assigned before listeners.
     *
     * @param string|object $event
     * @param array         $args
     *
     * @return bool
     **/
    protected function callOnListeners($event, array $args = []) : bool
    {
        $result = false;
        foreach ($this->getOnListeners($this->confirmEvent($event)) as $listener) {
            call_user_func($listener, ...$args);
            $result = true;
        }
        return $result;
    }

    /**
     * @param string|object $event
     */
    protected function confirmEvent($event) : string
    {
        return is_object($event) ? get_class($event) : $event;
    }
}