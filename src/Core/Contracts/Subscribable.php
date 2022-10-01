<?php

namespace Koansu\Core\Contracts;

/**
 * Allow a listener to subscribe to published class events
 */
interface Subscribable extends HasListeners
{
    /**
     * Subscribe to event $event.
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function on($event, callable $listener);

}
