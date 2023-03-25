<?php
/**
 *  * Created by mtils on 25.02.2023 at 12:45.
 **/

namespace Koansu\Skeleton;

use Closure;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

use function call_user_func;

/**
 * This is a static interface to "the" logger of current application. It forwards
 * to IO::instance()->logger() but can easily be changed by assigning another
 * logger callable.
 */
class Log
{
    /**
     * @var callable
     */
    private static $logger;

    /**
     * Detailed debug information.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function debug(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function info(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function notice(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function warning(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function error(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function critical(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function alert(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * System is unusable.
     *
     * @param string  $message
     * @param array $context
     *
     * @return void
     */
    public static function emergency(string $message, array $context=[]) : void
    {
        self::log(__FUNCTION__, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string   $level
     * @param string  $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @see LoggerInterface
     */
    public static function log(string $level, string $message, array $context = []) : void
    {
        if (!self::$logger) {
            self::$logger = self::defaultLogger();
        }
        // wanted to call the property for performance reasons
        call_user_func(self::$logger, $level, $message, $context);
    }

    /**
     * Get the current logger
     *
     * @return callable
     */
    public static function getLogger() : callable
    {
        if (!self::$logger) {
            self::$logger = self::defaultLogger();
        }
        return self::$logger;
    }

    /**
     * Set a logger.
     *
     * @param callable $logger
     * @return void
     */
    public static function setLogger(callable $logger) : void
    {
        self::$logger = $logger;
    }

    /**
     * Create the default logger
     *
     * @return Closure
     */
    private static function defaultLogger() : Closure
    {
        return function ($level, $message, array $context=[]) {
            IO::instance()->logger()->log($level, $message, $context);
        };
    }
}