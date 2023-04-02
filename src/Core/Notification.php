<?php
/**
 *  * Created by mtils on 02.04.2023 at 10:22.
 **/

namespace Koansu\Core;

use OutOfBoundsException;
use Psr\Log\LogLevel;

use function property_exists;

/**
 * This is a simple object representing a notification like warning, error etc.
 *
 * @property-read string $message
 * @property-read string $level (See Psr\Log\LogLevel)
 */
class Notification
{
    /**
     * One additional constant that does not exist in LogLevel to make success
     * notifications.
     */
    public const SUCCESS = 'success';

    /**
     * @var string
     */
    private $message = '';

    /**
     * @var string
     */
    private $level = LogLevel::INFO;

    public function __construct(string $message, string $level=LogLevel::INFO)
    {
        $this->message = $message;
        $this->level = $level;
    }

    public function __isset($property) : bool
    {
        return property_exists($this, $property);
    }

    public function __get($property)
    {
        if ($property === 'message') {
            return $this->message;
        }
        if ($property === 'level') {
            return $this->level;
        }
        throw new OutOfBoundsException("Message has to property named '$property'");
    }

    public function __toString() : string
    {
        return $this->message;
    }
}