<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:56.
 **/

namespace Koansu\Routing\Contracts;

use Koansu\Core\Response;

/**
 * Interface InputHandler
 *
 * This is just a placeholder interface. From koansu point of view there is nothing
 * more needed to handle input than just one method (a Closure). So this interface
 * is just a helper to store the binding in an IOCContainer and to mark that it
 * (should) return a response.
 */
interface InputHandler
{
    /**
     * Handle the input and return a corresponding response
     *
     * @param Input $input
     *
     * @return Response
     */
    public function __invoke(Input $input) : Response;
}