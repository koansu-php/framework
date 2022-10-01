<?php

namespace Koansu\Tests\Core\DataStructures;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Koansu\Core\DataStructures\Sequence;
use Koansu\Core\Exceptions\ItemNotFoundException;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\TestCase;
use OutOfBoundsException;
use OutOfRangeException;

use function count;

class SequenceTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceof(ArrayAccess::class, $this->newList());
        $this->assertInstanceof(IteratorAggregate::class, $this->newList());
        $this->assertInstanceof(Countable::class, $this->newList());
    }

    /**
     * @test
     */
    public function construct_with_params_fills_list()
    {
        $this->assertEquals(['a', 'b'], $this->newList(['a', 'b'])->getSource());
    }

    /**
     * @test
     */
    public function append_appends_a_value()
    {
        $list = $this->newList()->append('a');
        $this->assertEquals(['a'], $list->getSource());
        $list->append('b')->append('c');
        $this->assertEquals(['a', 'b', 'c'], $list->getSource());
    }

    /**
     * @test
     */
    public function push_appends_a_value()
    {
        $list = $this->newList()->push('a');
        $this->assertEquals(['a'], $list->getSource());
    }

    /**
     * @test
     */
    public function prepend_prepends_a_value()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertEquals(['b', 'c', 'd', 'e'], $list->prepend('b')->getSource());
        $this->assertEquals(['a', 'b', 'c', 'd', 'e'], $list->prepend('a')->getSource());
    }

    /**
     * @test
     */
    public function insert_appends_a_value()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertEquals(['c', 'd', 'e', 'f'], $list->insert(count($list), 'f')->getSource());
    }

    /**
     * @test
     */
    public function insert_before_zero_throws_exception()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->expectException(OutOfRangeException::class);
        $list->insert(-1, 'f');
    }

    /**
     * @test
     */
    public function insert_after_count_throws_exception()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->expectException(OutOfRangeException::class);
        $list->insert(5, 'h');
    }

    /**
     * @test
     */
    public function indexOf_finds_index_on_string()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertEquals(0, $list->indexOf('c'));
        $this->assertEquals(1, $list->indexOf('d'));
        $this->assertEquals(2, $list->indexOf('e'));
    }

    /**
     * @test
     */
    public function contains_returns_bool_and_doesnt_throw_exceptions()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertTrue($list->contains('c'));
        $this->assertFalse($list->contains('i'));
        $this->assertTrue($list->contains('e'));
    }

    /**
     * @test
     */
    public function pop_removes_last_value()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertEquals(['c', 'd', 'e'], $list->getSource());
        $this->assertEquals('e', $list->pop());
        $this->assertEquals(['c', 'd'], $list->getSource());
    }

    /**
     * @test
     */
    public function pop_removes_value_in_middle()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertEquals(['c', 'd', 'e'], $list->getSource());
        $this->assertEquals('d', $list->pop(1));
        $this->assertEquals(['c', 'e'], $list->getSource());
    }

    /**
     * @test
     */
    public function indexOf_throws_exception_if_value_not_found()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->expectException(OutOfBoundsException::class);
        $list->indexOf('h');
    }

    /**
     * @test
     */
    public function remove_removes_string()
    {
        $list = $this->newList(['c', 'd', 'e']);
        $this->assertEquals(['c', 'd', 'e'], $list->getSource());
        $this->assertEquals('d', $list->remove('d'));
        $this->assertEquals(['c', 'e'], $list->getSource());
    }

    /**
     * @test
     */
    public function count_with_params_counts_values()
    {
        $list = $this->newList(str_split('abbcccdeeeee'));

        $this->assertEquals(1, $list->countValue('a'));
        $this->assertEquals(2, $list->countValue('b'));
        $this->assertEquals(3, $list->countValue('c'));
        $this->assertEquals(1, $list->countValue('d'));
        $this->assertEquals(5, $list->countValue('e'));
    }

    /**
     * @test
     */
    public function sort_sorts_array_alphabetical()
    {
        $list = $this->newList(str_split('feddcba'));
        $this->assertEquals(str_split('abcddef'), $list->sort()->getSource());
    }

    /**
     * @test
     */
    public function reverse_sorts_reverse()
    {
        $list = $this->newList(str_split('fedcba'));
        $this->assertEquals(str_split('abcdef'), $list->reverse()->getSource());
    }

    /**
     * @test
     */
    public function unique_removes_duplicate_strings()
    {
        $list = $this->newList(str_split('abcdddefffg'));
        $this->assertEquals(str_split('abcdefg'), $list->unique()->getSource());
    }

    /**
     * @test
     */
    public function apply_calls_on_every_item()
    {
        $list = $this->newList(str_split('abcdef'));
        $callable = new LoggingCallable();

        $list->apply($callable);
        $this->assertCount(count($list), $callable);

        foreach ($list as $i=>$value) {
            $this->assertEquals($value, $callable->arg(0, $i));
        }
    }

    /**
     * @test
     */
    public function filter_filters_items()
    {
        $list = $this->newList(str_split('abcdef'));

        $filter = function ($item) {
            return $item != 'c';
        };

        $this->assertEquals(str_split('abdef'), $list->filter($filter)->getSource());
    }

    /**
     * @test
     */
    public function find_finds_item()
    {
        $list = $this->newList(str_split('abcdef'));

        $filter = function ($item) {
            return $item == 'c';
        };

        $this->assertEquals('c', $list->find($filter));
    }

    /**
     * @test
     */
    public function find_throws_exception_on_miss()
    {
        $list = $this->newList(str_split('abcdef'));

        $filter = function ($item) {
            return $item == 'r';
        };

        $this->expectException(ItemNotFoundException::class);
        $list->find($filter);
    }

    /**
     * @test
     */
    public function offsetGet_throws_exception_on_miss()
    {
        $list = $this->newList(str_split('abcdef'));
        $this->expectException(OutOfRangeException::class);
        $list[8];
    }

    /**
     * @test
     */
    public function offsetSet_sets_value()
    {
        $list = $this->newList(str_split('abcdef'));
        $list[6] = 'g';
        $this->assertEquals(str_split('abcdefg'), $list->getSource());
    }

    /**
     * @test
     */
    public function offsetExists()
    {
        $list = $this->newList(str_split('abcdef'));
        $this->assertTrue(isset($list[0]));
        $this->assertTrue(isset($list[3]));
        $this->assertFalse(isset($list[53]));
    }

    /**
     * @test
     */
    public function offsetUnset()
    {
        $list = $this->newList(str_split('abcdef'));
        unset($list[5]);
        $this->assertEquals(str_split('abcde'), $list->getSource());
    }

    /**
     * @test
     */
    public function construct_with_string_splits()
    {
        $this->assertEquals(str_split('abcdef'), $this->newList('abcdef')->getSource());
    }

    /**
     * @test
     */
    public function construct_with_int_creates_range()
    {
        $this->assertEquals(range(0, 10), $this->newList(10)->getSource());
    }

    /**
     * @test
     */
    public function construct_with_char_creates_range()
    {
        $this->assertEquals(range('A', 'Z'), $this->newList('Z')->getSource());
        $this->assertEquals(range('a', 'z'), $this->newList('z')->getSource());
    }

    /**
     * @test
     */
    public function construct_with_object_takes_items()
    {
        $list1 = $this->newList('abcdefg');
        $list2 = $this->newList($list1);
        $this->assertEquals($list1->getSource(), $list2->getSource());
    }

    /**
     * @test
     */
    public function construct_with_unteraversable_object_throws_exception()
    {
        $this->expectException(InvalidArgumentException::class);
        $list1 = $this->newList(new \stdClass());
    }

    /**
     * @test
     */
    public function first_returns_first_item()
    {
        $this->assertEquals('a',  $this->newList(str_split('abcdef'))->first());
        $this->assertNull($this->newList()->first());
    }

    /**
     * @test
     */
    public function last_returns_last_item()
    {
        $this->assertEquals('f',  $this->newList(str_split('abcdef'))->last());
        $this->assertNull($this->newList()->last());
    }

    /**
     * @test
     */
    public function copy_returns_new_instance()
    {
        $list = $this->newList(str_split('bcdef'));
        $copy = $list->copy();
        $this->assertNotSame($list, $copy);
        $this->assertEquals($list->getSource(), $copy->getSource());
        $list->append('g');
        $copy->prepend('a');

        $this->assertEquals(str_split('bcdefg'), $list->getSource());
        $this->assertEquals(str_split('abcdef'), $copy->getSource());
    }

    /**
     * @test
     */
    public function clone_returns_new_instance()
    {
        $list = $this->newList(str_split('bcdef'));
        $copy = clone $list;
        $this->assertNotSame($list, $copy);
        $this->assertEquals($list->getSource(), $copy->getSource());
        $list->append('g');
        $copy->prepend('a');

        $this->assertEquals(str_split('bcdefg'), $list->getSource());
        $this->assertEquals(str_split('abcdef'), $copy->getSource());
    }

    protected function newList($params=null)
    {
        return new Sequence($params);
    }
}
