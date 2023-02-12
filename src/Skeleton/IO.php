<?php
/**
 *  * Created by mtils on 02.11.2022 at 06:33.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\ExtendableTrait;
use Koansu\Skeleton\Contracts\InputConnection;
use Koansu\Skeleton\Contracts\OutputConnection;
use OutOfBoundsException;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use TypeError;

use function php_sapi_name;

/**
 * The IO class handles input and output. What input and output the application
 * uses is determined ONCE and by the environment. So a few examples would be
 * - Started by nginx/apache you would have an Input filled by $_SERVER and Output written
 *   to php://stdout
 * - Started by cli you would have an Input filled by argv and output to a terminal
 * - Started as a queue worker input would be read from a queue, output to log
 * - Started in a serverless environment, Kafka consumer, ...would create the
 *   corresponding connections to read and write
 *
 */
class IO implements Extendable
{
    use ExtendableTrait;

    /**
     * @var IO
     */
    protected static $staticInstance;

    /**
     * @var ?InputConnection
     */
    protected $input;

    /**
     * @var ?OutputConnection
     */
    protected $output;

    /**
     * @var ?LoggerInterface
     */
    protected $log;

    public function __construct()
    {
        static::$staticInstance = $this;
    }

    /**
     * Get the input connection to read from input.
     *
     * @return InputConnection
     */
    public function in() : InputConnection
    {
        if ($this->input) {
            return $this->input;
        }
        $input = $this->callUntilNotNull($this->_extensions, ['in', $this]);
        if ($input && !$input instanceof InputConnection) {
            throw new TypeError('The matching extension did not return an ' . InputConnection::class);
        }
        $this->input = $input ?: $this->createDefaultInput();
        return $this->input;
    }

    /**
     * Get the connection to write the output (stream).
     *
     * @return OutputConnection
     */
    public function out() : OutputConnection
    {
        if ($this->output) {
            return $this->output;
        }
        $output = $this->callUntilNotNull($this->_extensions, ['out', $this]);
        if ($output && !$output instanceof OutputConnection) {
            throw new TypeError('The matching extension did not return an ' . OutputConnection::class);
        }
        $this->output = $output ?: $this->createDefaultOutput();
        return $this->output;
    }

    /**
     * Return the application logger.
     *
     * @return LoggerInterface
     */
    public function logger() : LoggerInterface
    {
        if ($this->log) {
            return $this->log;
        }
        $logger = $this->callUntilNotNull($this->_extensions, ['log', $this]);
        if ($logger && !$logger instanceof LoggerInterface) {
            throw new TypeError('The matching extension did not return an ' . LoggerInterface::class);
        }
        $this->log = $logger ?: static::createDefaultLogger();
        return $this->log;
    }

    /**
     * Clear one or all connections.
     *
     * @param string[]|string $connections
     * @return void
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function clear($connections=['in', 'out', 'log'])
    {
        foreach ((array)$connections as $connection) {
            switch ($connection) {
                case 'in':
                    $this->input = null;
                    break;
                case 'out':
                    $this->output = null;
                    break;
                case 'log':
                    $this->log = null;
                    break;
                default:
                    throw new OutOfBoundsException("Unknown connection name $connection");
            }
        }
    }

    /**
     * Shortcut for logging a message.
     *
     * @see LoggerInterface::log()
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function log(string $level, string $message, array $context=[]) : void
    {
        $logger = static::$staticInstance ? static::$staticInstance->logger() : static::createDefaultLogger();
        $logger->log($level, $message, $context);
    }

    protected function createDefaultInput() : InputConnection
    {
        if (php_sapi_name() == 'cli') {
            return new ConsoleInputConnection();
        }
        return new HttpInputConnection();
    }

    protected function createDefaultOutput() : OutputConnection
    {
        if (php_sapi_name() == 'cli') {
            return new ConsoleOutputConnection();
        }
        return new HttpOutputConnection();
    }

    protected static function createDefaultLogger() : LoggerInterface
    {
        return new StreamLogger();
    }
}