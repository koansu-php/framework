<?php

namespace Koansu\Core\Contracts;

/**
 * The Hookable interface assures that you can hook before or after a
 * method or an event was performed.
 * So if your object has for example a method 'perform' you could hook into it
 * via onBefore('perform', f()) or onAfter('perform', d()).
 * If the events can't be seen as method hooks there might be no need to check
 * for event existence. You can also support object based events and
 * collect the listeners by ($fired instanceof $event).
 **/
interface Hookable extends HasListeners
{
    /**
     * Be informed before event (or method) $event is triggered.
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function onBefore($event, callable $listener);

    /**
     * Be informed before event (or method) $event is triggered.
     *
     * @param string|object|string[]|object[] $event
     * @param callable                        $listener
     *
     * @return void
     **/
    public function onAfter($event, callable $listener);

}
