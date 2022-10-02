<?php
/**
 *  * Created by mtils on 01.10.2022 at 11:34.
 **/

namespace Koansu\Core\DataStructures;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\Core\Exceptions\ItemNotFoundException;
use Koansu\Core\Type;

use function array_filter;
use function array_unshift;
use function call_user_func;
use function get_class;
use function spl_object_hash;

/**
 * An ObjectSet is an unordered storage of objects. It provides some helper
 * methods to help you to implement chain of responsibility and similar patterns.
 *
 * In general a set is unordered. But to help use it as an object stack with an
 * order it supports all methods in "insertion order" and reversed insertion
 * order.
 */
class ObjectSet implements IteratorAggregate, Countable
{
    /**
     * @var object[]
     */
    protected $objects = [];

    protected $objectKeys = [];
    protected $objectKeysReversed = [];

    /**
     * @var bool
     */
    private $compareByClass;

    public function __construct(bool $compareByClass=false)
    {
        $this->compareByClass = $compareByClass;
    }

    /**
     * Add an object to this set (once)
     *
     * @param object $object
     * @return void
     */
    public function add(object $object)
    {
        $key = $this->objectKey($object);
        if (isset($this->objects[$key])) {
            return;
        }
        $this->objects[$key] = $object;
        $this->objectKeys[] = $key;
        array_unshift($this->objectKeysReversed, $key);

    }

    /**
     * Remove an object from this class.
     *
     * @param object $object
     * @return void
     */
    public function remove(object $object)
    {
        $key = $this->objectKey($object);
        if (!isset($this->objects[$key])) {
            throw new ItemNotFoundException('The object that you try to remove is not in this set.');
        }
        unset($this->objects[$key]);
        $filter = function ($known) use ($key) {
            return $known != $key;
        };
        $this->objectKeys = array_filter($this->objectKeys, $filter);
        $this->objectKeysReversed = array_filter($this->objectKeysReversed, $filter);
    }

    /**
     * Return true if the set contains $object.
     *
     * @param object $object
     * @return bool
     */
    public function contains(object $object) : bool
    {
        return isset($this->objects[$this->objectKey($object)]);
    }

    /**
     * Remove all added objects.
     *
     * @return void
     */
    public function clear()
    {
        $this->objects = [];
        $this->objectKeys = [];
        $this->objectKeysReversed = [];
    }

    /**
     * Iterate the set.
     *
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->objects);
    }

    /**
     * Return the quantity of added objects.
     * @return int
     */
    public function count() : int
    {
        return count($this->objects);
    }

    /**
     * Get the first object (in insertion order) that return something cast to
     * true when calling $method with $args.
     *
     * Use this to build chain of responsibilities with separate methods like:
     * $set->firstObjectThat('canRender', ['application/html'])
     *
     * @param string $method
     * @param array $args (optional)
     * @return object
     *
     * @throws HandlerNotFoundException
     */
    public function firstObjectThat(string $method, array $args=[]) : object
    {
        return $this->firstObjectBy($this->matcher($method, $args));
    }

    /**
     * Get the last added object that return something cast to
     * true when calling $method with $args.
     *
     * Use this to build chain of responsibilities with separate methods like:
     * $set->firstObjectThat('canRender', ['application/html'])
     *
     * @param string $method
     * @param array $args (optional)
     * @return object
     *
     * @throws HandlerNotFoundException
     */
    public function lastObjectThat(string $method, array $args=[]) : ?object
    {
        return $this->lastObjectBy($this->matcher($method, $args));
    }

    /**
     * Take all objects in insertion order, give it $matcher and if matcher
     * returns something true return the object that was passed to the matcher.
     *
     * @param callable $matcher
     * @return object
     *
     * @throws HandlerNotFoundException
     */
    public function firstObjectBy(callable $matcher) : object
    {
        return $this->findObject($this->objectKeys, $matcher);
    }

    /**
     * Take all objects in reversed insertion order, give it $matcher and if
     * matcher returns something true return the object that was passed to the
     * matcher.
     *
     * @param callable $matcher
     * @return object
     *
     * @throws HandlerNotFoundException
     */
    public function lastObjectBy(callable $matcher) : object
    {
        return $this->findObject($this->objectKeysReversed, $matcher);
    }

    /**
     * Return the first result when calling all added objects in insertion order
     * by $method with $args.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws HandlerNotFoundException
     */
    public function firstResultCalling(string $method, array $args=[])
    {
        return $this->firstResultBy($this->caller($method, $args));
    }

    /**
     * Return the first result when calling all added objects in reversed
     * insertion order by $method with $args.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws HandlerNotFoundException
     */
    public function lastResultCalling(string $method, array $args=[])
    {
        return $this->lastResultBy($this->caller($method, $args));
    }

    /**
     * Iterate over all objects in insertion order and call $getter with it. If
     * $getter returns !== null return the result.
     *
     * @param callable $getter
     * @return mixed
     *
     * @throws HandlerNotFoundException
     */
    public function firstResultBy(callable $getter)
    {
        return $this->getResult($this->objectKeys, $getter);
    }

    /**
     * Iterate over all objects in reversed insertion order and call $getter
     * with it. If $getter returns !== null return the result.
     *
     * @param callable $getter
     * @return mixed
     *
     * @throws HandlerNotFoundException
     */
    public function lastResultBy(callable $getter)
    {
        return $this->getResult($this->objectKeysReversed, $getter);
    }

    /**
     * Return all results calling $method with $args on every object in insertion
     * order.
     *
     * @param string $method
     * @param array $args (optional)
     * @return array
     */
    public function allResultsCalling(string $method, array $args=[]) : array
    {
        return $this->map($this->caller($method, $args));
    }

    /**
     * Return all results calling $method with $args on every object in reversed
     * insertion order.
     *
     * @param string $method
     * @param array $args (optional)
     * @return array
     */
    public function allResultsCallingReversed(string $method, array $args=[]) : array
    {
        return $this->mapReversed($this->caller($method, $args));
    }

    /**
     * Apply $caller to every object in insertion order and collect its results
     * in an array and return this array.
     *
     * @param callable $caller
     * @return array
     */
    public function map(callable $caller) : array
    {
        return $this->getResults($this->objectKeys, $caller);
    }

    /**
     * Apply $caller to every object in reversed insertion order and collect its
     * results in an array and return this array.
     *
     * @param callable $caller
     * @return array
     */
    public function mapReversed(callable $caller) : array
    {
        return $this->getResults($this->objectKeysReversed, $caller);
    }

    /**
     * Return an instance that compares by class names and not object ids.
     *
     * @return ObjectSet
     */
    public static function byClass() : ObjectSet
    {
        return new static(true);
    }

    /**
     * @return bool
     */
    public function doesCompareByClass() : bool
    {
        return $this->compareByClass;
    }

    /**
     * @param string[] $keys
     * @param callable $matcher
     * @return object
     */
    protected function findObject(array $keys, callable $matcher) : object
    {
        foreach ($keys as $key) {
            $candidate = $this->objects[$key];
            if ($matcher($candidate)) {
                return $candidate;
            }
        }
        throw new HandlerNotFoundException('No handler matched by the passed matcher', HandlerNotFoundException::NO_HANDLERS_FOUND);
    }

    /**
     * Return the first result by a candidate.
     *
     * @param array $keys
     * @param callable $caller
     * @return mixed
     */
    protected function getResult(array $keys, callable $caller)
    {
        foreach ($keys as $key) {
            $candidate = $this->objects[$key];
            $result = $caller($candidate);
            if ($result !== null) {
                return $result;
            }
        }
        throw new HandlerNotFoundException('No handler returned a result', HandlerNotFoundException::NO_HANDLER_ANSWERED);
    }

    /**
     * Return all results by calling every object.
     *
     * @param array $keys
     * @param callable $caller
     * @return array
     */
    protected function getResults(array $keys, callable $caller) : array
    {
        $results = [];
        foreach ($keys as $key) {
            $candidate = $this->objects[$key];
            $results[] = $caller($candidate);
        }
        return $results;
    }

    /**
     * @param object $object
     * @return string
     */
    protected function objectKey(object $object) : string
    {
        if (!$this->compareByClass) {
            return spl_object_hash($object);
        }
        return $object instanceof Closure ? Type::closureClassId($object) : get_class($object);
    }

    /**
     * Make the matcher. Overwrite this method to match with a different strategy
     *
     * @param string $method
     * @param array $args
     *
     * @return Closure
     */
    protected function matcher(string $method, array $args) : Closure
    {
        return $this->caller($method, $args);
    }

    /**
     * Create the caller for $method, $args helper methods.
     *
     * @param string $method
     * @param array $args
     *
     * @return Closure
     */
    protected function caller(string $method, array $args) : Closure
    {
        return function ($candidate) use ($method, $args) {
            return call_user_func([$candidate, $method], ...$args);
        };
    }
}