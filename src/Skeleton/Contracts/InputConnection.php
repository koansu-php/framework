<?php
/**
 *  * Created by mtils on 01.11.2022 at 09:33.
 **/

namespace Koansu\Skeleton\Contracts;

use Koansu\Routing\Contracts\Input;
use Koansu\Core\Contracts\Connection;

/**
 * The
 */
interface InputConnection extends Connection
{
    /**
     * Is this connection open for further requests? In case of console, daemons
     * or pipes possibly yes. In http requests typically no.
     *
     * @return bool
     */
    public function isInteractive() : bool;

    /**
     * Receive the input. Use the returned input to process one. Pass a callable
     * to receive input.
     *
     * @param ?callable $into
     *
     * @return Input
     */
    public function read(callable $into=null) : Input;
}