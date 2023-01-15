<?php
/**
 *  * Created by mtils on 26.10.2022 at 20:09.
 **/

namespace Koansu\Routing\Contracts;

/**
 * This is a helper interface that marks a class to be "dependent" on a request.
 * Classes like controllers or ResponseFactory always depends on input. To reduce
 * the need to pass the request through every class this interface is used.
 *
 */
interface UtilizesInput
{
    /**
     * @param Input $input
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setInput(Input $input);
}