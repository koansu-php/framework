<?php
/**
 *  * Created by mtils on 23.10.2022 at 17:59.
 **/

namespace Koansu\Routing;

/**
 * Class Option
 *
 * In option is a parameter passed to a console command with one or two minus
 * console assets:copy --recursive -v --max=3
 */
class Option extends ConsoleParameter
{
    /**
     * A shortcut to allow fast access.
     *
     * @var string
     */
    public $shortcut ='';
}