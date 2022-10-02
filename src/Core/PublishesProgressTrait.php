<?php
/**
 *  * Created by mtils on 02.10.2022 at 08:58.
 **/

namespace Koansu\Core;

use Koansu\Core\Contracts\PublishesProgress;

use function call_user_func;

/**
 * Use this trait to implement:
 * @see PublishesProgress
 */
trait PublishesProgressTrait
{
    /**
     * @var array
     */
    protected $progressListeners = [];

    /**
     * Call a listener when the progress of the implementing
     * owner changes. The $listener will be called with a
     * Progress object.
     *
     * @param callable $listener
     *
     * @see Progress
     */
    public function onProgressChanged(callable $listener)
    {
        $this->progressListeners[] = $listener;
    }

    /**
     * Emit a progress object to all listeners.
     *
     * @param Progress|int $progress
     * @param int $step (default:0)
     * @param int $totalSteps (default:1)
     * @param string $stepName (optional)
     * @param int $leftOverSeconds (default:0)
     */
    protected function emitProgress($progress, int $step=0, int $totalSteps=1, string $stepName='', int $leftOverSeconds=0)
    {

        if (!$progress instanceof Progress) {
            $progress =  new Progress($progress, $step, $totalSteps, $stepName, $leftOverSeconds);
        }

        foreach ($this->progressListeners as $listener) {
            call_user_func($listener, $progress);
        }
    }
}