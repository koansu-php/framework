<?php
/**
 *  * Created by mtils on 01.11.2022 at 09:37.
 **/

namespace Koansu\Skeleton\Contracts;

use Koansu\Core\Contracts\Connection;

interface OutputConnection extends Connection
{
    /**
     * Write the output. Usually just echo it. Return if something
     * was actually written
     *
     * @param string|object $output
     * @param bool $lock
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function write($output, bool $lock=false) : bool;
}