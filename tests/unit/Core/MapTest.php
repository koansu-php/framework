<?php
/**
 *  * Created by mtils on 17.12.17 at 11:02.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\Map;
use Koansu\Tests\TestCase;
use stdClass;

class MapTest extends TestCase
{

    /**
     * @test
     */
    public function any_returns_true_on_any_hit()
    {
        $items = [11,5,'hello'];
        $this->assertTrue(Map::any($items, 'is_string'));
    }

    /**
     * @test
     */
    public function any_returns_false_on_no_hit()
    {
        $items = [11,5,new stdClass()];
        $this->assertFalse(Map::any($items, 'is_string'));
    }

    /**
     * @test
     */
    public function any_returns_false_on_empty()
    {
        $items = [];
        $this->assertFalse(Map::any($items, 'is_string'));
    }

    /**
     * @test
     */
    public function all_returns_true_on_all_matching()
    {
        $items = ['a', 'b', 'hello'];
        $this->assertTrue(Map::all($items, 'is_string'));
    }

    /**
     * @test
     */
    public function all_returns_false_on_all_matching()
    {
        $items = ['a', 15, 'hello'];
        $this->assertFalse(Map::all($items, 'is_string'));
    }

    /**
     * @test
     */
    public function all_returns_false_on_empty()
    {
        $items = [];
        $this->assertFalse(Map::all($items, 'is_string'));
    }
}