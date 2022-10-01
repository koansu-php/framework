<?php

namespace Koansu\Tests;

use Ems\Contracts\Core\Type;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Traversable;

use function count;
use function in_array;
use function is_callable;
use function realpath;
use function rtrim;
use function var_dump;

class TestCase extends BaseTestCase
{
    public function mock($class)
    {
        return Mockery::mock($class);
    }

    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * Assert that $collection has an object with
     *
     * @param array|Traversable $collection
     * @param array|callable    $criterion
     * @param string            $message (optional)
     */
    protected function assertHasObjectWith($collection, $criterion, string $message='')
    {

        $filtered = $this->getObjectsWith($collection, $criterion);
        $criteria = $this->formatCriteria($criterion);
        $message = $message ?: "Failed asserting that the passed collection contained the passed $criteria";
        $this->assertTrue(count($filtered) > 0, $message);
    }

    /**
     * Assert that $collection has $count (N) objects with the passed criterion,
     *
     * @param iterable          $collection
     * @param array|callable    $criterion
     * @param int               $count
     * @param string            $message (optional)
     */
    protected function assertHasNObjectWith(iterable $collection, $criterion, int $count, string $message='')
    {

        $filtered = $this->getObjectsWith($collection, $criterion);
        $realCount = count($filtered);
        $criteria = $this->formatCriteria($criterion);
        $message = $message ?: "Failed asserting that the passed collection contained $count items of the passed $criteria. It contained $realCount.";
        $this->assertTrue($realCount == $count, $message);
    }

    /**
     * @param array             $expected
     * @param object            $actual
     * @param string|string[]   $except (optional)
     * @param string            $message (optional)
     */
    protected function assertObjectHasData(array $expected, object $actual, $except='', string $message='')
    {
        $except = (array)$except;

        foreach ($expected as $key=>$value) {
            if (in_array($key, $except)) {
                continue;
            }
            if (!isset($actual->$key)) {
                $this->fail($message ? $message : "Actual object has no property '$key'");
            }
            if ($actual->$key != $value) {
                $this->fail($message ? $message : "Actual object property '$key' differs from expected");
            }
        }
    }

    /**
     * Get the objects with $criterion out of $collection. Criterion can be an
     * array and every $key=>$value has to match a property with value. Or
     * you pass a callable to do the check by your own. Return true in your
     * callable to include the object in the result, false to exclude it.
     *
     * @param iterable          $collection
     * @param array|callable    $criterion
     *
     * @return array
     */
    protected function getObjectsWith(iterable $collection, $criterion) : array
    {

        $f = is_callable($criterion) ? $criterion : function ($object) use ($criterion) {
            foreach ($criterion as $key=>$value) {
                if (!isset($object->$key)) {
                    return false;
                }
                if ($object->$key != $value) {
                    return false;
                }
            }
            return true;
        };

        $filtered = [];

        foreach ($collection as $item) {
            if ($f($item)) {
                $filtered[] = $item;
            }
        }
        return $filtered;
    }

    /**
     * @param callable|array $criteria
     *
     * @return string
     */
    protected function formatCriteria($criteria) : string
    {
        if (is_callable($criteria)) {
            return 'criteria (callable)';
        }
        $items = [];
        foreach ($criteria as $key=>$value){
            $formattedValue = Type::isStringable($value) ? $value : Type::of($value);
            $items[] = "$key=$formattedValue";
        }
        return 'criteria (' . implode(', ', $items) . ')';
    }

    /**
     * @notest
     *
     * @param string $dir
     * @return string
     */
    protected static function dirOfTests(string $dir='') : string
    {
        return rtrim(realpath(__DIR__."/../../tests/" . $dir),'/');
    }
}