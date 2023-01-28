<?php
/**
 *  * Created by mtils on 20.12.2022 at 22:18.
 **/

namespace Koansu\Tests\Search;

use ArrayAccess;
use ArrayObject;
use IteratorAggregate;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Search\Contracts\Filterable;
use Koansu\Search\FilterableArray;
use Koansu\Tests\TestCase;
use TypeError;

class FilterableArrayTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interfaces()
    {
        $this->assertInstanceOf(Filterable::class, $this->make());
        $this->assertInstanceOf(ArrayAccess::class, $this->make());
        $this->assertInstanceOf(Arrayable::class, $this->make());
        $this->assertInstanceOf(IteratorAggregate::class, $this->make());
    }

    /**
     * @test
     */
    public function get_and_set_source()
    {
        $source = ['foo' => 'bar'];
        $array = $this->make();
        $this->assertSame([], $array->getSource());
        $this->assertSame($array, $array->setSource($source));
        $this->assertSame($source, $array->getSource());
    }

    /**
     * @test
     */
    public function set_non_array_Like_source_throws_error()
    {
        $this->expectException(TypeError::class);
        $this->make('hello');
    }

    /**
     * @test
     */
    public function set_non_iterable_source_throws_error()
    {
        $this->expectException(TypeError::class);
        $this->make(new class () implements ArrayAccess {
            function offsetExists($offset) : bool
            {}
            #[\ReturnTypeWillChange]
            public function offsetGet($offset){}
            #[\ReturnTypeWillChange]
            public function offsetSet($offset, $value){}
            #[\ReturnTypeWillChange]
            public function offsetUnset($offset){}
        });
    }

    /**
     * @test
     */
    public function ArrayAccess_forwards_to_source()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make($source);
        $this->assertTrue(isset($array['foo']));
        $this->assertFalse(isset($array['bar']));
        $this->assertEquals($source['foo'], $array['foo']);
        $this->assertEquals($source['a'], $array['a']);
        $array['a'] = 'c';
        $this->assertEquals('c', $array['a']);
        $this->assertSame($source['all'], $array['all']);
        unset($array['all']);
        $this->assertFalse(isset($array['all']));
    }

    /**
     * @test
     */
    public function toArray_returns_array()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make($source);
        $this->assertSame($source, $array->__toArray());
    }

    /**
     * @test
     */
    public function toArray_returns_arrayable()
    {
        $data = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $source = $this->make($data);
        $array = $this->make($source);
        $this->assertSame($data, $array->__toArray());
    }

    /**
     * @test
     */
    public function toArray_returns_iterable()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make(new ArrayObject($source));
        $this->assertSame($source, $array->__toArray());
    }

    /**
     * @test
     */
    public function clear_clears_on_array()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make($source);
        $array->clear();
        $this->assertEmpty($array->__toArray());
    }

    /**
     * @test
     */
    public function clear_clears_on_ArrayData()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make($this->make($source));
        $array->clear();
        $this->assertEmpty($array->__toArray());
    }

    /**
     * @test
     */
    public function clear_clears_selected_keys()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make($source);
        $array->clear(['a']);
        $this->assertTrue(isset($array['foo']));
        $this->assertFalse(isset($array['a']));
    }

    /**
     * @test
     */
    public function clear_clears_nothing_with_empty_array()
    {
        $source = ['foo' => 'bar', 'a' => 'b', 'all' => [1,2,3]];
        $array = $this->make($source);
        $array->clear([]);
        $this->assertSame($source, $array->__toArray());
    }

    /**
     * @test
     */
    public function filter_filters_exact_matches()
    {
        $source = [
            (object)['id' => 1, 'first_name' => 'Maria', 'last_name' => 'Tunningham', 'tags' => ['young','female']],
            (object)['id' => 2, 'first_name' => 'Marc', 'last_name' => 'I marc', 'tags' => ['young','male']],
            (object)['id' => 3, 'first_name' => 'Manon', 'last_name' => 'Off', 'tags' => ['young','female']],
        ];
        $array = $this->make($source)->disableFuzzySearch();
        $this->assertFalse($array->isFuzzySearchEnabled());
        $this->assertEquals([$source[2]], $array->filter('first_name', 'Manon')->__toArray());

        $this->assertEquals([$source[1]], $array->filter('tags', ['young','male'])->__toArray());
    }

    /**
     * @test
     */
    public function filter_filters_fuzzy_matches()
    {
        $source = [
            (object)['id' => 1, 'first_name' => 'Maria', 'last_name' => 'Tunningham', 'tags' => ['young','female']],
            (object)['id' => 2, 'first_name' => 'Marc', 'last_name' => 'I marc', 'tags' => ['young','male']],
            (object)['id' => 3, 'first_name' => 'Manon', 'last_name' => 'Off', 'tags' => ['young','female']],
        ];
        $array = $this->make($source)->enableFuzzySearch();
        $this->assertTrue($array->isFuzzySearchEnabled());
        $this->assertEquals([$source[2]], $array->filter('first_name', 'Man?n')->__toArray());

        $this->assertEquals($source, $array->filter('first_name', 'ma*')->__toArray());
    }

    protected function make($source=[]) : FilterableArray
    {
        return new FilterableArray($source);
    }
}