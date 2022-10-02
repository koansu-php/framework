<?php
/**
 *  * Created by mtils on 02.10.2022 at 08:56.
 **/

namespace Koansu\Core\Contracts;

/**
 * If you have a long-running command implement this interface to publish the
 * progress to a listener.
 */
interface PublishesProgress
{
    /**
     * Call a listener when the progress of the implementing
     * owner changes. The $listener will be called with a
     * Progress object.
     *
     * @param callable $listener
     *
     * @see Progress
     */
    public function onProgressChanged(callable $listener);
}