<?php /** @noinspection PhpUnused */

/** @noinspection PhpMissingParamTypeInspection */

/**
 *  * Created by mtils on 04.02.2023 at 20:14.
 **/

namespace Koansu\Validation;

use ArrayAccess;
use Countable;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use Koansu\Core\ConstraintParsingTrait;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\ExtendableTrait;
use Koansu\Core\MethodExposingTrait;
use Koansu\Core\PointInTime;
use Koansu\Core\Str;
use Koansu\Core\Type;
use Koansu\Expression\Constraint;
use Koansu\Expression\ConstraintGroup;
use Koansu\Validation\Exceptions\ConstraintViolationException;
use Throwable;
use Traversable;
use UnderflowException;

use function array_keys;
use function array_merge;
use function array_shift;
use function array_unique;
use function array_unshift;
use function array_values;
use function call_user_func;
use function checkdate;
use function date_parse;
use function explode;
use function filter_var;
use function func_get_args;
use function gettype;
use function in_array;
use function is_array;
use function is_bool;
use function is_iterable;
use function is_null;
use function is_numeric;
use function is_object;
use function is_string;
use function json_decode;
use function json_last_error;
use function mb_strlen;
use function method_exists;
use function preg_match;
use function preg_quote;
use function preg_split;
use function simplexml_load_string;
use function strip_tags;
use function strlen;
use function strpos;
use function strtolower;
use function strtotime;
use function trim;

use function ucfirst;
use function var_dump;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_IP;
use const FILTER_VALIDATE_URL;
use const JSON_ERROR_NONE;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

/**
 * The Matcher is a simple tool to match values if they match a constraint
 */
class Matcher
{
    use ExtendableTrait;
    use ConstraintParsingTrait;
    use MethodExposingTrait;

    /**
     * Verify that $value matches $rule.
     *
     * @param mixed                                   $value
     * @param ConstraintGroup|Constraint|array|string $rule
     * @param object|null                             $ormObject (optional)
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function match($value, $rule, $ormObject=null) : bool
    {

        $constraints = $this->ruleToArray($rule);

        foreach ($constraints as $name => $parameters) {

            $arguments = $parameters;

            array_unshift($arguments, $value);

            if ($ormObject) {
                $arguments[] = $ormObject; // Creates errors
            }

            if (!$this->__call($name, $arguments)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify the $value against $rule, when failed throw an exception.
     *
     * @param mixed                        $value
     * @param ConstraintGroup|Constraint|array|string $rule
     * @param object|null                  $ormObject (optional)
     *
     * @return bool (always true)
     *
     * @throws ConstraintViolationException
     * @noinspection PhpMissingParamTypeInspection
     */
    public function force($value, $rule, $ormObject=null) : bool
    {
        if (!$this->match($value, $rule, $ormObject)) {
            throw new ConstraintViolationException('Value does not match constraint.');
        }

        return true;
    }

    /**
     * Callable alias to self::match()
     *
     * @param mixed                                   $value
     * @param ConstraintGroup|Constraint|array|string $rule
     * @param object|null                             $ormObject (optional)
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __invoke($value, $rule, $ormObject=null) : bool
    {
        return $this->match($value, $rule, $ormObject);
    }

    /**
     * Check if the constraint $rule is supported.
     *
     * @param string $rule
     *
     * @return bool
     */
    public function supports(string $rule) : bool
    {
        if ($this->hasExtension($rule)) {
            return true;
        }

        return $this->getMethodByExposedName($rule) != '';
    }

    /**
     * Return all possible constraint names.
     *
     * @return string[]
     */
    public function names() : array
    {
        $all = array_merge($this->getExtensionNames(), array_keys($this->getExposedMethods()));
        return array_unique($all);
    }

    /**
     * Call a matcher directly.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments = [])
    {
        if (!$arguments) {
            throw new UnderflowException('You have to pass at least one parameter (value) to match something');
        }

        if ($this->hasExtension($name)) {
            return call_user_func($this->getExtension($name), ...$arguments);
        }

        if ($methodName = $this->getMethodByExposedName($name)) {
            return $this->$methodName(...$arguments);
        }

        // If the passed name is the "not snake case" or somehow different version
        $methodName = $this->getExposedMethodPrefix() . $name;

        if ($this->isNativeMethodOfExposed($methodName)) {
            return $this->$methodName(...$arguments);
        }

        throw new ImplementationException("Constraint '$name' is not supported.");

    }

    /**
     * Verify if the value equals another value.
     *
     * @param mixed $value
     * @param mixed $other
     *
     * @return bool
     */
    protected function matchEquals($value, $other) : bool
    {
        return $value == $other;
    }

    /**
     * Check exactly if $value is $other.
     *
     * @param mixed $value
     * @param mixed $other
     *
     * @return bool
     */
    protected function matchIs($value, $other) : bool
    {
        return $this->matchCompare($value, 'is', $other);
    }

    /**
     * Check if $value is not exactly $other.
     *
     * @param mixed $value
     * @param mixed $other
     *
     * @return bool
     */
    protected function matchIsNot($value, $other) : bool
    {
        return $this->matchCompare($value, 'is not', $other);
    }

    /**
     * Check if the value is not equal to another value.
     *
     * @param mixed $value
     * @param mixed $other
     *
     * @return bool
     */
    protected function matchNotEqual($value, $other) : bool
    {
        return !$this->matchEquals($value, $other);
    }

    /**
     * Check if the passed value is set.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchRequired($value) : bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if ((is_array($value) || $value instanceof Countable) && count($value) < 1) {
            return false;
        }

        return true;
    }

    /**
     * Check if the passed value is greater than or equals parameter.
     *
     * @param $value
     * @param $limit
     *
     * @return bool
     */
    protected function matchMin($value, $limit) : bool
    {
        return $this->matchCompare($value, '>=', $limit);
    }

    /**
     * Check if the passed value is less than or equals parameter.
     *
     * @param $value
     * @param $limit
     *
     * @return bool
     */
    protected function matchMax($value, $limit) : bool
    {
        return $this->matchCompare($value, '<=', $limit);
    }

    /**
     * Check if the passed value is greater than the parameter.
     *
     * @param $value
     * @param $limit
     *
     * @return bool
     */
    protected function matchGreater($value, $limit) : bool
    {
        return $this->matchCompare($value, '>', $limit);
    }

    /**
     * Check if the passed value is less than the parameter.
     *
     * @param $value
     * @param $limit
     *
     * @return bool
     */
    protected function matchLess($value, $limit) : bool
    {
        return $this->matchCompare($value, '<', $limit);
    }

    /**
     * Check if a value is between $min and $max. (inclusive)
     *
     * @param $value
     * @param $min
     * @param $max
     *
     * @return bool
     */
    protected function matchBetween($value, $min, $max) : bool
    {
        return $this->matchMin($value, $min) && $this->matchMax($value, $max);
    }

    /**
     * Check if $value has exactly $size.
     *
     * @param mixed $value
     * @param $size
     *
     * @return bool
     */
    protected function matchSize($value, $size) : bool
    {
        return $this->matchCompare($this->getSize($value), '=' , $size);
    }

    /**
     * Check if $date is after $earliest. It is assumed the $date comes from "outside"
     * like http, an import,...so the earliest is not parsed
     * using the format by default. But it will make a second try using it.
     *
     * @param $date
     * @param $earliest
     * @param string $format
     *
     * @return bool
     */
    protected function matchAfter($date, $earliest, string $format='') : bool
    {
        try {
            return $this->toTimestamp($date, $format) > $this->tryWithoutAndWithFormat($earliest, $format);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Check if $date is before $earliest. It is assumed the $date comes from "outside"
     * like http, an import,...so the earliest is not parsed
     * using the format by default. But it will make a second try using it
     *
     * @param $date
     * @param $latest
     * @param string $format
     *
     * @return bool
     */
    protected function matchBefore($date, $latest, string $format='') : bool
    {
        try {
            return $this->toTimestamp($date, $format) < $this->tryWithoutAndWithFormat($latest, $format);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Check if the value is of type $type. (class or type)
     *
     * @param mixed  $value
     * @param string $type
     *
     * @return bool
     */
    protected function matchType($value, $type) : bool
    {
        return Type::is($value, $type);
    }

    /**
     * Check if a value is an integer.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchInt($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Check if a value looks like a boolean.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function matchBool($value) : bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    /**
     * Check if a value is numeric.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchNumeric($value) : bool
    {
        return is_numeric($value);
    }

    /**
     * Check if a value is a string.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchString($value) : bool
    {
        return Type::isStringLike($value);
    }

    /**
     * Compare $first and $second by $operator
     *
     * @param mixed  $left
     * @param string $operator
     * @param mixed  $right
     * @param bool   $strict (default:false)
     *
     * @return bool
     */
    protected function matchCompare($left, string $operator, $right, bool $strict=false) : bool
    {

        if ($strict || in_array($operator, ['is', 'is not', '=', '!=', '<>'])) {
            return $this->isComparable($left, $right) && $this->compare($left, $operator, $right);
        }

        if (!$comparable = $this->makeComparable($left, $right)) {
            return false;
        }

        list($left, $right) = $comparable;

        return $this->compare($left, $operator, $right);
    }

    /**
     * Checks if a value would be counted as true.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function matchTrue($value) : bool
    {
        return Type::toBool($value) === true;
    }

    /**
     * Checks if a value would be counted as true.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function matchFalse($value) : bool
    {
        return Type::toBool($value) === false;
    }

    /**
     * Check if the passed $value is contained in $list.
     *
     * @param mixed $value
     * @param mixed $list
     *
     * @return bool
     */
    protected function matchIn($value, $list) : bool
    {
        // List is passed as second parameter or with many single parameters
        $args = func_get_args();
        array_shift($args); // remove $value

        $items = count($args) > 1 ? $args : (array)$list;

        return in_array($value, $items);
    }

    /**
     * Return true if the passed $value is not in $list.
     *
     * @param mixed $value
     * @param mixed $list
     *
     * @return bool
     */
    protected function matchNotIn($value, $list) : bool
    {
        $args = func_get_args();
        return !$this->matchIn(...$args);
    }

    /**
     * Check if the passed $value is a valid date. Date can be only day or date
     * and time
     *
     * @param $value
     * @param string $format
     *
     * @return bool
     */
    protected function matchDate($value, string $format='') : bool
    {

        if ($value instanceof PointInTime) {
            return $value->isValid();
        }

        if ($value instanceof DateTimeInterface) {
            return true;
        }

        // Support for legacy datetime objects like Zend_Date
        if (is_object($value) && method_exists($value, 'getTimestamp')) {
            return $this->matchInt($value->getTimestamp());
        }

        if (((!Type::isStringLike($value) && !is_numeric($value)) || strtotime($value) === false) && !$format) {
            return false;
        }

        if (!$format) {
            $date = date_parse($value);

            return checkdate($date['month'], $date['day'], $date['year']);
        }

        try {
            $dateTime = DateTime::createFromFormat($format, $value);
            return $dateTime instanceof DateTime;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Check if value is a valid date and time. If a string contains no hour
     * or the point in time has no time it returns false.
     *
     * @param $value
     * @param string $format
     * @return bool
     */
    protected function matchDatetime($value, string $format='') : bool
    {
        if ($value instanceof PointInTime) {
            return $value->isValid() && in_array($value->precision(), [PointInTime::HOUR, PointInTime::MINUTE, PointInTime::SECOND]);
        }
        if ($value instanceof DateTimeInterface) {
            return true;
        }
        if (!Type::isStringLike($value)) {
            return $this->matchDate($value, $format);
        }
        $dateString = (string)$value;
        // No time passed
        if (!$format) {
            return strpos($dateString, ':') && $this->matchDate($dateString);
        }

        $timeFormatCharFound = false;
        // all time format chars
        foreach (['a','A','B','g','G','h','H','i','s','u','v','c','r','U'] as $char) {
            if (strpos($format, $char)) {
                $timeFormatCharFound = true;
            }
        }
        if (!$timeFormatCharFound) {
            return false;
        }
        return $this->matchDate($value, $format);
    }

    /**
     * Check if the passed value is a valid time (clock)
     * @param $value
     * @param string $format
     * @return bool
     */
    protected function matchTime($value, string $format='') : bool
    {
        if ($value instanceof PointInTime) {
            return $value->isValid() && in_array($value->precision(), [PointInTime::HOUR, PointInTime::MINUTE, PointInTime::SECOND]);
        }
        if ($value instanceof DateTimeInterface) {
            return true;
        }
        if (!Type::isStringLike($value)) {
            return $this->matchDate($value, $format);
        }
        if ($format) {
            return @DateTime::createFromFormat($format, $value) !== false;
        }
        if (!strpos($value, ':')) {
            return false;
        }
        $parts = explode(':', $value);
        $partCount = count($parts);
        if ($partCount < 2 || $partCount > 3) {
            return false;
        }
        $hour = (int)$parts[0];
        $minutes = (int)$parts[1];
        $seconds = 0;
        if ($partCount > 2) {
            $seconds = $parts[2];
        }
        return $hour > -1 && $hour < 24 && $minutes > -1 && $minutes < 60 && $seconds > -1 && $seconds < 59;
    }

    /**
     * Check if the passed value is a valid email address
     *
     * @param string $value
     *
     * @return bool
     */
    protected function matchEmail(string $value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if the passed value is a valid url.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function matchUrl($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if the passed value is a valid ip address.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchIp($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if the passed value is a valid ip v4 address.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchIpv4($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Check if the passed value is a valid ip v4 address.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchIpv6($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Check if a number of $count were passed.
     *
     * @param mixed $value
     * @param int    $count
     *
     * @return bool
     */
    protected function matchDigits($value, $count) : bool
    {

        if (is_numeric($value)) {
            $value = "$value";
        }

        if (!Type::isStringLike($value)) {
            return false;
        }

        return ! preg_match('/[^0-9]/', "$value")
            && strlen("$value") == $count;
    }

    /**
     * Check if the passed $value is json.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchJson($value) : bool
    {
        if (!Type::isStringLike($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if the passed $value is (valid) xml.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchXml($value) : bool
    {
        if (!Type::isStringLike($value)) {
            return false;
        }

        return (bool)@simplexml_load_string("$value");
    }

    /**
     * Checks if $value is html.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchHtml($value) : bool
    {
        if (!Type::isStringLike($value)) {
            return false;
        }

        $string = "$value";

        return $string != strip_tags($string);
    }

    /**
     * Checks if $value is no html.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchPlain($value) : bool
    {
        if (!Type::isStringable($value)) {
            return false;
        }

        $string = "$value";

        return $string == strip_tags($string);
    }

    /**
     * Check if a string is plain or has the allowed $htmlTags.
     * Note that because using strip_tags html comments are also not allowed
     * here. In opposite to strip_tags the tags are passed as an array.
     *
     * @param string       $value
     * @param string|array $htmlTags
     *
     * @return bool
     */
    protected function matchTags($value, $htmlTags) : bool
    {
        if (!Type::isStringable($value)) {
            return false;
        }

        $args = func_get_args();
        array_shift($args); // remove $value

        $htmlTags = count($args) > 1 ? $args : (array)$htmlTags;

        $tagString = '';

        foreach ($htmlTags as $tag) {
            $tag = trim($tag);
            $tagString .= "<$tag><$tag/><$tag />";
        }

        $string = "$value";

        return $string == strip_tags($string, $tagString);

    }

    /**
     * Check if a string has exactly $count chars. In opposite to matchSize it
     * will do an explicit cast to string and will not match the size of countable
     * objects.
     *
     * @param mixed $value
     * @param int $count
     *
     * @return bool
     */
    protected function matchChars($value, $count) : bool
    {
        if (!Type::isStringable($value)) {
            return false;
        }

        return mb_strlen("$value") == $count;
    }

    /**
     * Check if a string contains exactly $count words. Numbers are also words.
     *
     * @param mixed $value
     * @param int $count
     *
     * @return bool
     */
    protected function matchWords($value, $count) : bool
    {
        if (!Type::isStringLike($value)) {
            return $count == 0;
        }

        return count(preg_split('~[^\p{L}\p{N}\']+~u', $value, -1, PREG_SPLIT_NO_EMPTY)) == $count;
    }

    /**
     * Check if a string starts with $start.
     *
     * @param string $value
     * @param string $start
     *
     * @return bool
     */
    protected function matchStartsWith($value, $start) : bool
    {
        if (!Type::isStringable($value)) {
            return false;
        }

        return Str::stringStartsWith("$value", $start);
    }

    /**
     * Check if a string ends with $start.
     *
     * @param string $value
     * @param string $end
     *
     * @return bool
     */
    protected function matchEndsWith($value, $end) : bool
    {
        if (!Type::isStringable($value)) {
            return false;
        }

        return Str::stringEndsWith("$value", $end);
    }

    /**
     * Check if $value is somewhere inside $value. Strings and arrays are supported.
     *
     * @param string|array $value
     * @param mixed $needle
     *
     * @return bool
     */
    protected function matchContains($value, $needle) : bool
    {
        if (Type::isStringLike($value)) {
            return Str::stringContains($value, $needle);
        }
        foreach ((array)$needle as $item) {
            if (is_array($value)) {
                if (in_array($item, array_values($value))) {
                    return true;
                }
            }
            if (!is_iterable($value)) {
                return false;
            }
            foreach ($value as $haystack) {
                if ($haystack == $item) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * To a sql like match on $pattern.
     *
     * @param string $value
     * @param string $pattern
     * @param string $escape (default: \)
     *
     * @return bool
     */
    protected function matchLike($value, $pattern, $escape='\\') : bool
    {

        if (!Type::isStringable($value) || !Type::isStringable($pattern)) {
            return false;
        }

        $value = "$value";
        $pattern = "$pattern";

        // @see https://stackoverflow.com/questions/11434305/simulating-like-in-php

        // Split the pattern into special sequences and the rest
        $expr = '/((?:'.preg_quote($escape, '/').')?(?:'.preg_quote($escape, '/').'|%|_))/';
        $parts = preg_split($expr, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Loop the split parts and convert/escape as necessary to build regex
        $expr = '/^';

        $lastWasPercent = false;

        foreach ($parts as $part) {
            switch ($part) {
                case $escape.$escape:
                    $expr .= preg_quote($escape, '/');
                    break;
                case $escape.'%':
                    $expr .= '%';
                    break;
                case $escape.'_':
                    $expr .= '_';
                    break;
                case '%':
                    if (!$lastWasPercent) {
                        $expr .= '.*?';
                    }
                    break;
                case '_':
                    $expr .= '.';
                    break;
                default:
                    $expr .= preg_quote($part, '/');
                    break;
            }

            $lastWasPercent = $part == '%';

        }

        $expr .= '$/i';

        // Look for a match and return bool
        return (bool)preg_match($expr, $value);
    }

    /**
     * Return true if $value matches $pattern.
     *
     * @param mixed $value
     * @param string $pattern
     *
     * @return bool
     */
    protected function matchRegex($value, $pattern) : bool
    {

        if (!Type::isStringable($value)) {
            return false;
        }

        return preg_match($pattern, "$value") > 0;
    }

    /**
     * Check that a string contains only alphabetic chars.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchAlpha($value) : bool
    {
        return $this->matchRegex($value, '/^[\pL\pM]+$/u');
    }

    /**
     * Check that a string contains only alphanumeric chars and dashes (- and _).
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchAlphaDash($value) : bool
    {
        return $this->matchRegex($value, '/^[\pL\pM\pN_-]+$/u');
    }

    /**
     * Check that a string contains alphanumeric chars.
     *
     * @param $value
     *
     * @return bool
     */
    protected function matchAlphaNum($value) : bool
    {
        return $this->matchRegex($value, '/^[\pL\pM\pN]+$/u');
    }

    /**
     * Check the length. This is useful if you have strings that are numeric but
     * you want to count chars not the integer value.
     * Pass a numeric parameter to match $value == $parameter.
     * Pass a string with two numeric values divided by a minus to match
     * if the length is between two values ("3-45").
     *
     * @param mixed            $value
     * @param int|float|string $parameter
     * @return bool
     */
    protected function matchLength($value, $parameter) : bool
    {
        $size = $this->getSize($value, false);

        if (is_numeric($parameter)) {
            return $size == $parameter;
        }
        $parts = explode('-',$parameter);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("matchLength either accepts a numeric value for equal comparison or two numbers divided by a minus.");
        }
        return $size >= $parts[0] && $size <= $parts[1];
    }

    /**
     * Check if something is an array (or behaves like an array)
     *
     * @param mixed $value
     * @return bool
     */
    protected function matchArray($value) : bool
    {
        if (is_array($value)) {
            return true;
        }
        return $value instanceof ArrayAccess && $value instanceof Traversable && $value instanceof Countable;
    }

    /**
     * Make two values comparable by normal operators.
     *
     * @param $value
     * @param $parameter
     *
     * @return array
     */
    protected function makeComparable($value, $parameter) : array
    {

        if (!$value instanceof DateTimeInterface && !$parameter instanceof DateTimeInterface) {
            return [$this->getSize($value), $parameter];
        }

        // One is datetime
        try {
            $value = $this->toTimestamp($value);
            $parameter = $this->toTimestamp($parameter);
            return [$value, $parameter];
        } catch (InvalidArgumentException $e) {
            return [];
        }

    }

    /**
     * Check if two values are comparable. ([] > 4 evaluates to true in php...)
     * This is somehow by definition...
     *
     * @param mixed $left
     * @param mixed $right
     *
     * @return bool
     */
    protected function isComparable($left, $right) : bool
    {

        if (gettype($left) == gettype($right)) {
            return true;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return true;
        }

        // You can compare anything to bool and anything to null (really?...)
        return is_bool($left) || is_bool($right) || is_null($left) || is_null($right);

    }

    /**
     * Try to convert a arbitrary date parameter into a timestamp.
     *
     * @param mixed $date
     * @param string $format
     * @return int
     */
    protected function toTimestamp($date, string $format='') : int
    {
        $obj = $format ? PointInTime::createFromFormat($format, $date) : PointInTime::guessFrom($date);
        if (!$obj) {
            throw new InvalidArgumentException("Unable to parse date '$date'");
        }
        return $obj->getTimestamp();
    }

    /**
     * @param $date
     * @param string $format
     * @return int
     */
    protected function tryWithoutAndWithFormat($date, string $format) : int
    {
        if (!$format) {
            return $this->toTimestamp($date);
        }
        try {
            return $this->toTimestamp($date);
        } catch (InvalidArgumentException $e) {
            return $this->toTimestamp($date, $format);
        }
    }

    /**
     * Try to guess the size of a parameter.
     *
     * @param mixed $value
     * @param bool $matchNumeric
     *
     * @return int
     */
    protected function getSize($value, bool $matchNumeric=true) : int
    {
        if ($matchNumeric && is_numeric($value)) {
            return $value;
        }

        if (is_array($value) || $value instanceof Countable) {
            return count($value);
        }

        return $value === null ? 0 : mb_strlen($value);
    }

    /**
     * @param $rule
     *
     * @return array
     */
    protected function ruleToArray($rule) : array
    {
        if ($rule instanceof ConstraintGroup || $rule instanceof Constraint) {
            return $this->constraintToArray($rule);
        }

        return $this->parseConstraint($rule);
    }

    /**
     * @param Constraint|ConstraintGroup $rule
     *
     * @return array
     */
    protected function constraintToArray($rule) : array
    {
        if ($rule instanceof ConstraintGroup) {
            return $this->constraintGroupToArray($rule);
        }

        $operator = $rule->operator();

        // Do some more strict checks if constraints with operators were passed
        if (!$operator) {
            return [$rule->name() => $rule->parameters()];
        }

        if (strtolower($operator) == 'like') {
            return ['like' => $rule->parameters()];
        }

        return [
            'compare' => [
                $operator,
                $rule->parameters()[0],
                true
            ]
        ];
    }

    /**
     * @param ConstraintGroup $group
     *
     * @return array
     */
    protected function constraintGroupToArray(ConstraintGroup $group) : array
    {
        $array = [];

        foreach ($group->constraints() as $constraint) {
            foreach ($this->constraintToArray($constraint) as $key=>$value) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Compare $left and $right by $operator.
     *
     * @param mixed  $left
     * @param string $operator
     * @param mixed  $right
     *
     * @return bool
     */
    protected function compare($left, string $operator, $right) : bool
    {
        switch ($operator) {
            case '<':
                return $left < $right;
            case '>':
                return $left > $right;
            case '<=':
                return $left <= $right;
            case '>=':
                return $left >= $right;
            case '!=':
            case '<>':
                return $left != $right;
            case '=':
                return $left == $right;
            case 'is':
                return $left === $right;
            case 'is not':
                return $left !== $right;
            default:
                throw new InvalidArgumentException("Unknown operator '$operator");
        }
    }
    protected function getExposedMethodPrefix(): string
    {
        return 'match';
    }


}