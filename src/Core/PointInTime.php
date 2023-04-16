<?php
/**
 *  * Created by mtils on 04.02.2023 at 21:19.
 **/

namespace Koansu\Core;

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

use OutOfBoundsException;

use function call_user_func;
use function date_create;
use function date_default_timezone_get;
use function is_numeric;
use function is_object;
use function method_exists;

/**
 * Class PointInTime
 *
 * @property int $year
 * @property int $month
 * @property int $day
 * @property int $hour
 * @property int $minute
 * @property int $second
 * @property int $timezone
 * @property int $timestamp
 * @property int $offset
 * @property int $precision
 */
class PointInTime extends DateTime
{
    public const YEAR = 'Y';

    public const MONTH = 'm';

    public const DAY = 'd';

    public const HOUR = 'H';

    public const MINUTE = 'i';

    public const SECOND = 's';

    public const TIMEZONE = 'e';

    public const WEEKDAY = 'l';

    /**
     * @var string
     **/
    protected $precision;

    /**
     * @var bool
     */
    protected $isInValid = false;

    protected $properties = [
        'year'      => self::YEAR,
        'month'     => self::MONTH,
        'day'       => self::DAY,
        'hour'      => self::HOUR,
        'minute'    => self::MINUTE,
        'second'    => self::SECOND,
        'timezone'  => 'getTimeZone',
        'timestamp' => 'getTimestamp',
        'offset'    => 'getOffset',
        'precision' => 'precision',
    ];

    protected $propertyAccess = [
        'year'      => 'format',
        'month'     => 'format',
        'day'       => 'format',
        'hour'      => 'format',
        'minute'    => 'format',
        'second'    => 'format',
        'timezone'  => 'getTimeZone',
        'timestamp' => 'getTimestamp',
        'offset'    => 'getOffset',
        'precision' => 'precision',
    ];

    /**
     * @param string|null $time
     * @param DateTimeZone|string|null $tz
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __construct($time = '', $tz = null)
    {

        if ($time instanceof None) {
            $this->invalidate();
            $time = '';
        }

        try {
            parent::__construct($time, $tz);
        } catch (Exception $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            parent::__construct('', $tz);
        }

    }

    /**
     * Return the precision (self::YEAR|self::MONTH...).
     *
     * @return string
     *
     * @see self::YEAR
     **/
    public function precision() : string
    {
        if ($this->precision) {
            return $this->precision;
        }

        return self::SECOND;
    }

    /**
     * Set what this precision is (self::SECOND).
     *
     * @param string $precision
     *
     * @return $this
     **/
    public function setPrecision(string $precision) : PointInTime
    {
        $this->precision = $precision;

        return $this;
    }

    /**
     * You can invalidate a PointInTime Object. Normally there is no
     * "null"-state of a DateTime object. But in many cases I just
     * want to have an invalid DateTime object.
     *
     * @return bool
     */
    public function isValid() : bool
    {
        return !$this->isInValid;
    }

    /**
     * Make this datetime object invalid.
     *
     * @param bool $makeInvalid
     *
     * @return self
     */
    public function invalidate(bool $makeInvalid = true) : PointInTime
    {
        $this->isInValid = $makeInvalid;
        return $this;
    }


    /**
     * @param mixed $property
     * @return int
     **/
    public function __get($property)
    {
        if (!isset($this->properties[$property])) {
            throw new OutOfBoundsException("Property $property is not supported");
        }

        switch ($property) {
            case 'year':
            case 'month':
            case 'day':
            case 'hour':
            case 'minute':
            case 'second':
                return (int) $this->format($this->properties[$property]);
            case 'timezone':
            case 'timestamp':
            case 'offset':
            case 'precision':
                return call_user_func([$this, $this->properties[$property]]);
        }

        return null;

    }

    /**
     * @param string $property
     * @param mixed  $value
     **/
    public function __set($property, $value)
    {
        if (!isset($this->properties[$property])) {
            throw new OutOfBoundsException("Property $property is not supported");
        }

        switch ($property) {
            case 'year':
                $this->setDate($value, $this->month, $this->day);

                return;
            case 'month':
                $this->setDate($this->year, $value, $this->day);

                return;
            case 'day':
                $this->setDate($this->year, $this->month, $value);

                return;
            case 'hour':
                $this->setTime($value, $this->minute, $this->second);

                return;
            case 'minute':
                $this->setTime($this->hour, $value, $this->second);

                return;
            case 'second':
                $this->setTime($this->hour, $this->minute, $value);

                return;
            case 'timezone':
                $this->setTimezone($value);

                return;
            case 'timestamp':
                $this->setTimestamp($value);

                return;
            case 'offset':
                throw new OutOfBoundsException('You cannot set an offset, assign a new DateTimeZone');

            case 'precision':
                $this->setPrecision($value);
        }
    }

    /**
     * @param string $property
     *
     * @return bool
     **/
    public function __isset($property)
    {
        return isset($this->properties[$property]);
    }

    /**
     * @param string            $format
     * @param string            $datetime
     * @param DateTimeZone|null $timezone (optional)
     *
     * @return PointInTime
     */
    #[\ReturnTypeWillChange]
    public static function createFromFormat($format, $datetime, DateTimeZone $timezone = null)
    {
        $timezone = $timezone ?: new DateTimeZone(date_default_timezone_get());
        if (!$other = DateTime::createFromFormat($format, $datetime, $timezone)) {
            throw new InvalidArgumentException("Unable to parse date '$datetime' with format '$format'");
        }

        return (new static())->setTimestamp($other->getTimestamp())
            ->setTimezone($other->getTimezone());
    }

    /**
     * Try to guess the datetime by the passed string.
     *
     * @param mixed $date
     *
     * @return static
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function guessFrom($date) : PointInTime
    {
        if ($date instanceof DateTime) {
            return (new static())->setTimestamp($date->getTimestamp())->setTimezone($date->getTimezone());
        }

        if (is_numeric($date)) {
            return (new static())->setTimestamp((int) $date);
        }

        if (is_object($date) && method_exists($date, 'getTimestamp')) {
            return (new static())->setTimestamp($date->getTimestamp());
        }

        if (!Type::isStringLike($date)) {
            $typeName = Type::of($date);
            throw new InvalidArgumentException("No idea how to cast $typeName to DateTime");
        }

        $date = (string) $date;

        if ($dateTime = date_create($date)) {
            return static::guessFrom($dateTime);
        }

        throw new InvalidArgumentException("No idea how to cast $date to DateTime");
    }
}