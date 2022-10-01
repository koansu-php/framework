<?php
/**
 *  * Created by mtils on 17.12.17 at 10:56.
 **/

namespace Koansu\Core;

use Closure;
use Traversable;
use function call_user_func;

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
}