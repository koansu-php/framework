<?php
/**
 *  * Created by mtils on 28.10.2022 at 17:25.
 **/

namespace Koansu\Routing;

use Koansu\Core\Response;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\InputHandler as InputHandlerContract;

/**
 * Class CallableAsInputHandler
 *
 * This class is to create an input handler on the fly out of your callable.
  */
class CallableAsInputHandler implements InputHandlerContract
{
    /**
     * @var callable
     */
    protected $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * {@inheritDoc}
     *
     * @param Input $input
     * @return Response
     */
    public function __invoke(Input $input): Response
    {
        return call_user_func($this->callable, $input);
    }

}