<?php

namespace Koansu\Core\Contracts;

/**
 * This is the base interface of Hookable|Subscribable
 **/
interface HasListeners
{
    /**
     * Return all listeners for event $event. Ask for position be $position
     * 'after' or 'before'.
     *
     * @param string|object $event
     * @param string $position ('after'|'before'|'')
     *
     * @return callable[]|iterable
     **/
    public function getListeners($event, string $position = '') : iterable;
}
