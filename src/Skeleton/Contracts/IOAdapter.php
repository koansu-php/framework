<?php
/**
 *  * Created by mtils on 14.04.2023 at 08:42.
 **/

namespace Koansu\Skeleton\Contracts;

use Koansu\Routing\Contracts\Input;

interface IOAdapter
{
    /**
     * Run the main loop and call $handler with input and the output handler.
     *
     * @param callable<Input, callable> $handler
     * @return void
     */
    public function read(callable $handler) : void;

    /**
     * Return true if use can interact inside main loop. A console command is
     * interactive because it can ask questions or pause, a http request, queue
     * worker or event handler is not.
     *
     * @return bool
     */
    public function isInteractive() : bool;

    /**
     * Alias for self::read() to use it in application.
     *
     * @param callable<Input, callable> $handler
     * @return void
     */
    public function __invoke(callable $handler) : void;

}