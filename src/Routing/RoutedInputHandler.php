<?php
/**
 *  * Created by mtils on 28.10.2022 at 16:02.
 **/

namespace Koansu\Routing;

use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\DependencyInjection\Contracts\Container;
use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Core\Type;
use Koansu\Routing\Contracts\InputHandler as InputHandlerContract;
use Koansu\Routing\Contracts\UrlGenerator as UrlGeneratorContract;
use Koansu\Routing\Contracts\UtilizesInput;
use Koansu\Core\Exceptions\ConfigurationException;
use Koansu\Routing\Contracts\Input as InputContract;
use Koansu\DependencyInjection\Lambda;
use Koansu\Core\HookableTrait;
use Koansu\Core\Response;
use Koansu\Core\CustomFactoryTrait;
use Koansu\Http\HttpResponse;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;

use function is_callable;
use function print_r;

/**
 * Class RoutedInputHandler
 *
 * This class does the actual running of the route handler.
 * The Input has to be routed before calling __invoke. Otherwise, it will just
 * throw an exception.
 *
 * @package Ems\Routing
 */
class RoutedInputHandler implements InputHandlerContract, SupportsCustomFactory, HasMethodHooks
{
    use CustomFactoryTrait;
    use HookableTrait;

    public function __construct(callable $customFactory=null)
    {
        $this->_customFactory = $customFactory;
    }

    /**
     * Handle the input and return a corresponding response
     *
     * @param InputContract $input
     *
     * @return Response
     * @throws ReflectionException
     */
    public function __invoke(InputContract $input) : Response
    {
        if (!$input->isRouted()) {
            throw new ConfigurationException('The input has to be routed to get handled by ' . static::class . '.');
        }

        $handler = $input->getHandler();

        if(!is_callable($handler)) {
            throw new ConfigurationException('The input says it is routed but missing a callable handler. getHandler() returned a ' . Type::of($handler));
        }

        if ($handler instanceof Lambda) {
            $this->configureLambda($handler, $input);
        }

        $this->callBeforeListeners('call', [$handler, $input]);

        $response = $this->respond($input, $this->call($handler, $input));

        $response = $this->configureResponse($input, $response);

        $this->callAfterListeners('call', [$handler, $input, $response]);

        return $response;

    }

    /**
     * {@inheritDoc}
     *
     * @return string[]
     **/
    public function methodHooks() : array
    {
        return ['call'];
    }


    /**
     * Call the handler.
     *
     * @param callable $handler
     * @param InputContract $input
     *
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function call(callable $handler, InputContract $input)
    {
        return call_user_func($handler, ...array_values($input->getRouteParameters()));
    }

    /**
     * Create the response from the handlers output.
     *
     * @param InputContract $input
     * @param mixed $result
     *
     * @return Response
     */
    protected function respond(InputContract $input, $result) : Response
    {
        if($result instanceof Response) {
            return $result;
        }

        if (in_array($input->getClientType(), [InputContract::CLIENT_CONSOLE, InputContract::CLIENT_TASK])) {
            return is_int($result) ? new Response(null, [], $result) : new Response($result);
        }

        return new HttpResponse($result);
    }

    protected function configureResponse(InputContract $input, Response $response) : Response
    {
        if (!$response instanceof HttpResponse) {
            return $response;
        }
        if ($input->getUrl()->scheme == 'http') {
            return $response->withSecureCookies(false);
        }
        return $response;
    }

    /**
     * @param Lambda $handler
     * @param InputContract $input
     * @throws ReflectionException
     */
    protected function configureLambda(Lambda $handler, InputContract $input)
    {
        if ($this->_customFactory && !$handler->getInstanceResolver()) {
            $handler->setInstanceResolver($this->_customFactory);
        }
        if ($this->_customFactory instanceof Container) {
            $this->_customFactory->on(UtilizesInput::class, function (UtilizesInput $inputUser) use ($input) {
                $inputUser->setInput($input);
            });
            $this->_customFactory->bind(UrlGeneratorContract::class, function () use ($input) {
                /** @var UrlGenerator $urls */
                $urls = $this->_customFactory->create(UrlGenerator::class);
                return $urls->withInput($input);
            });
        }
        // Manually bind the current input to explicitly use the input of this
        // application call
        $handler->bind(InputContract::class, $input);
        //$handler->bind(Input::class, $input);

        if ($input instanceof ServerRequestInterface) {
            $handler->bind(ServerRequestInterface::class, $input);
        }

        if ($input instanceof HttpInput) {
            $handler->bind(HttpInput::class, $input);
        }

        if ($input instanceof ArgvInput) {
            $handler->bind(ArgvInput::class, $input);
        }

        if (!$handler->isInstanceMethod() || !$controller = $handler->getCallInstance()) {
            return;
        }

        if ($controller instanceof UtilizesInput) {
            $controller->setInput($input);
        }

    }
}