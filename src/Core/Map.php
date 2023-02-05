<?php
/**
 *  * Created by mtils on 17.12.17 at 10:56.
 **/

namespace Koansu\Core;

use Closure;

use function array_key_exists;
use function array_pop;
use function array_shift;
use function call_user_func;
use function explode;
use function is_array;
use function is_null;
use function preg_quote;
use function preg_split;
use function strlen;
use function substr;

class Map
{
    /**
     * Return true if $check returns true on ALL items.
     *
     * @param iterable  $items
     * @param callable  $check
     *
     * @return bool
     */
    public static function all(iterable $items, callable $check) : bool
    {
        foreach ($items as $item) {
            if (!call_user_func($check, $item)) {
                return false;
            }
        }

        // Return false if array is empty
        return isset($item);
    }

    /**
     * Return true if $check returns true on ANY item.
     *
     * @param iterable  $items
     * @param callable  $check
     *
     * @return bool
     */
    public static function any(iterable $items, callable $check) : bool
    {
        foreach ($items as $item) {
            if (call_user_func($check, $item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Combine all passed callables to one callable that will call them all.
     * The returned closure will return all results in an array.
     *
     * @param ...$callables
     * @return Closure
     */
    public static function combine(...$callables) : Closure
    {
        return function (...$args) use ($callables) {
            $results = [];
            foreach ($callables as $callable) {
                $results[] = $callable(...$args);
            }
            return $results;
        };
    }

    /**
     * Get a key from a nested array. Query a deeply nested array with
     * property.child.name.
     *
     * @param array  $nested
     * @param string $key
     * @param string $delimiter
     *
     * @return mixed
     **/
    public static function get(array $nested, string $key, string $delimiter = '.')
    {
        if ($key == '*') {
            return $nested;
        }

        if ($key == $delimiter) {
            return self::withoutNested($nested);
        }

        $delimiterLength = 0-strlen($delimiter);
        if($endsWithDelimiter = (substr($key, $delimiterLength) === $delimiter)) {
            $key = substr($key, 0, $delimiterLength);
        }


        if (isset($nested[$key])) {
            return $endsWithDelimiter ? self::withoutNested($nested[$key]) : $nested[$key];
        }

        foreach (explode($delimiter, $key) as $segment) {
            if (!is_array($nested) || !array_key_exists($segment, $nested)) {
                return null;
            }

            $nested = $nested[$segment];
        }

        return $endsWithDelimiter ? self::withoutNested($nested) : $nested;
    }

    /**
     * Set a (nested) key.
     *
     * @param array $array
     * @param $key
     * @param $value
     * @return void
     */
    public static function set(array &$array, $key, $value) : void
    {
        if (is_null($key)) {
            $array = $value;
            return;
        }

        $segments = is_array($key) ? $key : explode('.', $key);

        while (count($segments) > 1) {
            $key = array_shift($segments);

            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($segments)] = $value;
    }

    /**
     * Put a flat array in this method, and it will return a recursively nested
     * version. Separate the segments by $delimiter
     *
     * @param array  $flat
     * @param string $delimiter
     *
     * @return array
     **/
    public static function nest(array $flat, string $delimiter = '.') : array
    {
        $tree = [];

        foreach ($flat as $key => $val) {

            // Get parent parts and the current leaf
            $parts = self::splitPath($key, $delimiter);
            $leafPart = array_pop($parts);

            // Build parent structure
            $parent = &$tree;

            foreach ($parts as $part) {
                if (!isset($parent[$part])) {
                    $parent[$part] = [];
                } elseif (!is_array($parent[$part])) {
                    $parent[$part] = [];
                }

                $parent = &$parent[$part];
            }

            // Add the final part to the structure
            if (empty($parent[$leafPart])) {
                $parent[$leafPart] = $val;
            }
        }

        return $tree;
    }

    /**
     * Converts a nested array to a flat one.
     *
     * @param array  $nested    The nested source array
     * @param string $delimiter Levels connector
     *
     * @return array
     **/
    public static function flatten(array $nested, string $delimiter = '.') : array
    {
        $result = [];
        static::flattenArray($result, $nested, $delimiter);
        return $result;
    }

    /**
     * Splits the path into an array.
     *
     * @param string $path
     * @param string $separator (default:.)
     *
     * @return array
     **/
    public static function splitPath(string $path, string $separator = '.') : array
    {
        $regex = '/(?<=\w)('.preg_quote($separator, '/').')(?=\w)/';
        return preg_split($regex, $path, -1);//, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    }

    /**
     * Recursively converts nested array into a flat one with keys preserving.
     *
     * @param array  $result    Resulting array
     * @param array  $array     Source array
     * @param string $delimiter Levels connector
     * @param ?string $prefix    Key's prefix
     **/
    protected static function flattenArray(array &$result, array $array, string $delimiter = '.', string $prefix = null)
    {
        foreach ($array as $key => $value) {
            if (!is_array($value) || isset($value[0])) {
                $result[$prefix.$key] = $value;
                continue;
            }
            self::flattenArray($result, $value, $delimiter, $prefix.$key.$delimiter);
        }
    }

    /**
     * @param array $array
     * @return array
     */
    protected static function withoutNested(array $array) : array
    {
        $filtered = [];
        foreach ($array as $key=>$value) {
            if (!is_array($value)) {
                $filtered[$key] = $value;
                continue;
            }
            if (isset($value[0])) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}