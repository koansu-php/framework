<?php
/**
 *  * Created by mtils on 02.10.2022 at 08:54.
 **/

namespace Koansu\Core;

/**
 * This is a value object that is passed to a PublishesProgress listener.
 */
class Progress
{

    /**
     * Progress constructor.
     *
     * @param int    $percent    (default:0)
     * @param int    $step       (default:0)
     * @param int    $totalSteps (default:1)
     * @param string $stepName   (optional)
     * @param int    $leftOverSeconds (default:0)
     */
    public function __construct(int $percent=0, int $step=0, int $totalSteps=1, string $stepName='', int $leftOverSeconds=0)
    {
        $this->percent = $percent;
        $this->step = $step;
        $this->totalSteps = $totalSteps;
        $this->stepName = $stepName;
        $this->leftOverSeconds = $leftOverSeconds;
    }

    /**
     * How many percent of 100 was currently processed.
     *
     * @var int
     */
    public $percent = 0;

    /**
     * Which step is currently processed or the last processed
     * step.
     *
     * @var int
     */
    public $step = 0;

    /**
     * The total amount of steps for the current operation.
     *
     * @var int
     */
    public $totalSteps = 1;

    /**
     * An internal (technical) name of the current step.
     *
     * @var string
     */
    public $stepName = 'step';

    /**
     * The estimated leftover time to complete the operation.
     *
     * @var int
     */
    public $leftOverSeconds = 0;
}