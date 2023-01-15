<?php
/**
 *  * Created by mtils on 17.12.2022 at 08:52.
 **/

namespace Koansu\Skeleton;

use Koansu\Console\AnsiRenderer;
use Koansu\Core\Contracts\Extendable;
use Koansu\Core\ImmutableMessage;
use Koansu\Core\Type;
use Koansu\Http\HttpResponse;
use Koansu\Routing\ArgvInput;
use Koansu\Routing\Exceptions\HttpStatusException;
use Koansu\Routing\Exceptions\RouteNotFoundException;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\ResponseFactory;
use Koansu\Routing\Contracts\UtilizesInput;
use Koansu\Core\ExtendableTrait;
use Koansu\Core\Response;
use ErrorException;
use Koansu\Routing\HttpInput;
use Psr\Log\LoggerInterface;
use Throwable;

use function call_user_func;
use function error_get_last;
use function error_reporting;
use function get_class;
use function in_array;
use function php_sapi_name;
use function register_shutdown_function;
use function set_error_handler;
use function set_exception_handler;

use function var_dump;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_DEPRECATED;
use const E_ERROR;
use const E_PARSE;
use const E_USER_DEPRECATED;
use const E_USER_WARNING;
use const E_WARNING;

class ErrorHandler implements Extendable
{
    use ExtendableTrait;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var callable
     */
    protected $renderer;

    /**
     * @var null|bool
     */
    protected $logDeprecated;

    /**
     * @var bool
     */
    protected $logWarnings = true;

    /**
     * @var ?LoggerInterface
     */
    protected $logger;

    /**
     * ErrorHandler constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle the input.
     *
     * @param Throwable $e
     * @param Input|null $input
     *
     * @return Response
     */
    public function handle(Throwable $e, Input $input=null) : Response
    {
        $input = ImmutableMessage::lastByNext($input ?: $this->scrapeInput());
        $class = get_class($e);

        if ($this->hasExtension($class)) {
            return $this->callExtension($class, $this->getExtension($class), [$e, $input]);
        }

        if ($this->shouldLogError($e, $input)) {
            $this->logThrowable($e);
        }

        if ($this->shouldDisplayError($e, $input)) {
            return $this->render($e, $input);
        }

        $shortClass = Type::short($e);

        $status = $e instanceof HttpStatusException ? $e->getStatus() : 500;
        $message = "No Content ($shortClass)\n";
        return $this->respond($input, $message, $status);
    }

    public function receiveFromPhp(Throwable $e)
    {
        $response = $this->handle($e);

        if ($response instanceof HttpResponse) {
            echo $response->payload;
            return;
        }

        if ($response->contentType != 'text/x-console-lines') {
            echo $response->payload;
            return;
        }

        echo (new AnsiRenderer())->format($response->payload) . "\n";

    }

    /**
     * Use it as an input handler exception handler.
     *
     * @param Throwable  $e
     * @param Input|null $input
     *
     * @return Response
     */
    public function __invoke(Throwable $e, Input $input=null) : Response
    {
        return $this->handle($e, $input);
    }

    /**
     * Install the error handler and register it in php
     */
    public function install() : void
    {
        error_reporting(-1);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'receiveFromPhp']);
        register_shutdown_function([$this, 'checkShutdown']);
    }

    /**
     * @throws ErrorException If he should not catch it
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0, array $context=[])
    {
        if (!(error_reporting() && $level)) {
            return;
        }
        if (in_array($level,[E_WARNING, E_USER_WARNING])) {
            $this->handleWarning($message, $file, $line, $context);
            return;
        }
        if (in_array($level,[E_DEPRECATED, E_USER_DEPRECATED])) {
            $this->handleDeprecatedError($message, $file, $line, $context);
            return;
        }
        throw new ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * test for errors on shutdown
     */
    public function checkShutdown()
    {
        if (!$error = error_get_last()) {
            return;
        }
        if (!in_array($error["type"], [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE])) {
            return;
        }
        $this->receiveFromPhp(new ErrorException(
                          $error['message'], 0, $error['type'], $error['file'], $error['line']
                      ));
    }

    /**
     * @return callable
     */
    public function getRenderer(): callable
    {
        if (!$this->renderer) {
            $this->renderer = new ExceptionRenderer();
        }
        return $this->renderer;
    }

    /**
     * @param callable $renderer
     * @return ErrorHandler
     */
    public function setRenderer(callable $renderer): ErrorHandler
    {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * @return bool
     */
    public function shouldLogDeprecated() : bool
    {
        if ($this->logDeprecated !== null) {
            return $this->logDeprecated;
        }
        return $this->environment() !== Application::PRODUCTION;
    }

    /**
     * @return bool
     */
    public function shouldLogWarnings() : bool
    {
        return $this->logWarnings;
    }

    /**
     * @param bool $force
     * @return $this
     */
    public function forceLogOfDeprecated(bool $force=true) : ErrorHandler
    {
        $this->logDeprecated = $force;
        return $this;
    }

    /**
     * @param Input $input
     * @param string $message
     * @param int $status
     * @return Response
     */
    protected function respond(Input $input, string $message, int $status=500) : Response
    {
        /** @var ResponseFactory $responseFactory */
        $responseFactory = $this->app->get(ResponseFactory::class);
        if ($responseFactory instanceof UtilizesInput) {
            $responseFactory->setInput($input);
        }
        return $responseFactory->create($message)->withStatus($status);
    }

    /**
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return void
     */
    protected function handleWarning(string $message, string $file = '', int $line = 0, array $context=[]) : void
    {
        if ($this->shouldLogWarnings()) {
            $this->log('warning',"Warning: " . $message . " in $file($line)");
        }
    }

    /**
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return void
     */
    protected function handleDeprecatedError(string $message, string $file = '', int $line = 0, array $context=[]) : void
    {
        if ($this->shouldLogDeprecated()) {
            $this->log('debug',"Deprecated: " . $message . " in $file($line)");
        }
    }

    /**
     * @param Throwable $e
     */
    protected function logThrowable(Throwable $e)
    {
        $this->log('error', $this->formatException($e) . "\n");
    }

    /**
     * @param Throwable $e
     * @return string
     */
    protected function formatException(Throwable $e) : string
    {
        $message = 'Uncaught exception ' . get_class($e) . ' in ' . $e->getFile() . '(' . $e->getLine() . '): ';
        if ($code = $e->getCode()) {
            $message .= "Error #$code ";
        }
        $message .= $e->getMessage();
        return $message;
    }

    /**
     * Make a nice presentation of $e.
     *
     * @param Throwable $e
     * @param Input     $input
     *
     * @return Response
     */
    protected function render(Throwable $e, Input $input) : Response
    {
        return call_user_func($this->getRenderer(), $e, $input);
    }

    /**
     * Overwrite this method to control error display
     *
     * @param Throwable $e
     * @param Input     $input
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    protected function shouldDisplayError(Throwable $e, Input $input) : bool
    {
        return $this->environment() != Application::PRODUCTION;
    }

    /**
     * Overwrite this method to control error logging
     *
     * @param Throwable $e
     * @param Input     $input
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    protected function shouldLogError(Throwable $e, Input $input) : bool
    {
        if ($e instanceof RouteNotFoundException) {
            return false;
        }
        return $this->environment() == Application::PRODUCTION;
    }

    /**
     * @return string
     */
    protected function environment() : string
    {
        return $this->app->getEnvironment();
    }

    protected function scrapeInput() : Input
    {
        if ($input = $this->app->currentInput()) {
            return $input;
        }
        /** @var IO $io */
        $io = $this->app->get(IO::class);
        try {
            return $io->in()->read();
        } catch (Throwable $e) {
            if (php_sapi_name() == 'cli') {
                return new ArgvInput();
            }
            return new HttpInput();
        }

    }

    protected function log(string $level, string $message, $context=[])
    {
        if (!$this->logger) {
            $this->logger = $this->app->get(LoggerInterface::class);
        }
        $this->logger->log($level, $message, $context);
    }
}