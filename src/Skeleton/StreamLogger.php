<?php
/**
 *  * Created by mtils on 03.12.2022 at 12:42.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\Contracts\Chatty;
use Koansu\Core\Stream;
use Koansu\Core\Url;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Throwable;

use function get_class;
use function is_object;
use function json_encode;
use function spl_object_id;
use function strtoupper;
use function var_export;

use const JSON_PRETTY_PRINT;

/**
 * Class StreamLogger
 *
 * This is a small placeholder logger to have very basic support of a logger. In
 * your application you possibly better use Monolog or something similar.
 *
 */
class StreamLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var StreamInterface
     */
    protected $stream;

    /**
     * @var Url
     */
    protected $url;

    /**
     * StreamLogger constructor.
     *
     * @param string|StreamInterface|Url $target
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct($target='php://stderr')
    {
        $this->setTarget($target);
    }

    /**
     * Return the target where should the log be written
     * @return StreamInterface
     */
    public function getTarget() : StreamInterface
    {
        return $this->stream;
    }

    /**
     * Set were the log should land.
     *
     * @param StreamInterface|Url|string $target
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setTarget($target) : StreamLogger
    {
        if ($target instanceof StreamInterface) {
            $this->stream = $target;
            return $this;
        }
        $this->stream = new Stream($target, 'a');
        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array()) : void
    {
        $entry = $this->format($level, $message, $context);
        $this->stream->write("\n$entry");
    }

    /**
     * Forward the messages of this chatty object to log.
     *
     * @param Chatty $chatty
     */
    public function forward(Chatty $chatty)
    {
        $chatty->onMessage(function ($message, $level) {
            $this->log($message, $level === Chatty::FATAL ? 'critical' : $level);
        });
    }

    /**
     * Format a log entry.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function format($level, string $message, array $context=[]) : string
    {
        $date = date('Y-m-d H:i:s');

        $contextString = '';
        if ($context) {
            $contextString = $this->formatContext($context);
        }
        $type = strtoupper($level);
        return "## $date $type ## $message $contextString";
    }

    /**
     * Format the context of a log message.
     *
     * @param array $context
     * @return string
     */
    protected function formatContext(array $context) : string
    {
        $formatted = [];
        foreach ($context as $key=>$value) {
            if (is_array($value)) {
                $formatted[] = "$key => " . json_encode($value, JSON_PRETTY_PRINT, 4);
                continue;
            }
            if (!is_object($value)) {
                $formatted[] = "$key => " . var_export($value, true);
                continue;
            }
            if (!$value instanceof Throwable) {
                $formatted[] = "$key => Object #" . spl_object_id($value) . ' of class ' . get_class($value);
                continue;
            }

            $formatted[] = "$key => Exception " . get_class($value) . ' with message "' . $value->getMessage() . '"';
            $formatted[] = 'Trace: ' . $value->getTraceAsString();

        }

        return implode("\n", $formatted);
    }

}