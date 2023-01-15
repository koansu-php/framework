<?php
/**
 *  * Created by mtils on 26.10.2022 at 18:25.
 **/

namespace Koansu\Routing;

use Exception;
use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Core\Response;
use Koansu\Core\CustomFactoryTrait;
use Koansu\Core\ImmutableMessage;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\InputHandler as InputHandlerContract;
use Koansu\Routing\Contracts\MiddlewareCollection as MiddlewareCollectionContract;
use Throwable;

use function call_user_func;

class InputHandler implements InputHandlerContract, SupportsCustomFactory
{
    use CustomFactoryTrait;

    /**
     * @var callable
     */
    protected $exceptionHandler;

    /**
     * @var MiddlewareCollectionContract
     */
    protected $middleware;

    public function __construct(MiddlewareCollectionContract $middleware)
    {
        $this->middleware = $middleware;
    }

    /**
     * Handle the input and return a corresponding
     *
     * @param Input $input
     *
     * @return Response
     */
    public function __invoke(Input $input) : Response
    {
        // Better repeat that stuff than having any type of unwanted exception
        // trace steps.
        if (!$this->exceptionHandler) {
            return $this->middleware->__invoke($input);
        }

        try {
            return $this->middleware->__invoke($input);
        } catch (Exception $e) {
            return call_user_func($this->exceptionHandler, $e, $input);
        } catch (Throwable $e) {
            return call_user_func($this->exceptionHandler, $e, $input);
        }
    }

    /**
     * @return MiddlewareCollectionContract
     */
    public function middleware() : MiddlewareCollectionContract
    {
        return $this->middleware;
    }

    /**
     * @return callable
     */
    public function getExceptionHandler() : callable
    {
        return $this->exceptionHandler;
    }

    /**
     * Set an exception handler. It will receive any exception.
     *
     * @param callable $handler
     *
     * @return $this
     */
    public function setExceptionHandler(callable $handler) : InputHandler
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    /**
     * Find the last created input by a middleware
     *
     * @param Input $input
     * @return Input
     */
    protected function findLastCreatedInput(Input $input) : Input
    {
        if ($input instanceof ImmutableMessage && $input->next instanceof Input) {
            return $this->findLastCreatedInput($input->next);
        }
        return $input;
    }

}