<?php /** @noinspection SpellCheckingInspection */

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

    /**
     * @test
     */
    public function nest_with_one_level()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            'address.id'    => 578,
            'address.street'=> 'Elmstreet 13'
        ];

        $addressArray = [
            'id'     => 578,
            'street' => 'Elmstreet 13'
        ];

        $nested = Map::nest($array);

        $this->assertEquals($addressArray, $nested['address']);
    }

    /**
     * @test
     */
    public function flatten_with_one_level()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            'address.id'    => 578,
            'address.street'=> 'Elmstreet 13'
        ];

        $addressArray = [
            'id'     => 578,
            'street' => 'Elmstreet 13'
        ];

        $nested = Map::nest($array);

        $this->assertEquals($addressArray, $nested['address']);
        $this->assertEquals($array, Map::flatten($nested));
    }

    /**
     * @test
     **/
    public function nest_with_one_level_contains_list()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            'address.id'    => 578,
            'address.street'=> 'Elmstreet 13',
            'address.lang' => ['en', 'de', 'fr']
        ];

        $addressArray = [
            'id'     => 578,
            'street' => 'Elmstreet 13',
            'lang'   => ['en', 'de', 'fr']
        ];

        $nested = Map::nest($array);

        $this->assertEquals($addressArray, $nested['address']);
    }

    /**
     * @test
     **/
    public function flatten_with_one_level_contains_list()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            'address.id'    => 578,
            'address.street'=> 'Elmstreet 13',
            'address.lang' => ['en', 'de', 'fr']
        ];

        $addressArray = [
            'id'     => 578,
            'street' => 'Elmstreet 13',
            'lang'   => ['en', 'de', 'fr']
        ];

        $nested = Map::nest($array);

        $this->assertEquals($addressArray, $nested['address']);
        $this->assertEquals($array, Map::flatten($nested));
    }

    /**
     * @test
     **/
    public function nest_with_leading_separator_does_not_produce_children()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            '.id'           => 578,
            'address.street'=> 'Elmstreet 13'
        ];

        $nested = Map::nest($array);

        $resultArray = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            '.id'           => 578,
            'address'       => [
                'street' => 'Elmstreet 13'
            ]
        ];

        $this->assertEquals($resultArray, $nested);

    }

    /**
     * @test
     **/
    public function nest_with_trailing_separator_does_not_produce_children()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            'id.'           => 578,
            'address.street'=> 'Elmstreet 13'
        ];

        $nested = Map::nest($array);

        $resultArray = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            'id.'           => 578,
            'address'       => [
                'street' => 'Elmstreet 13'
            ]
        ];

        $this->assertEquals($resultArray, $nested);
    }

    /**
     * @test
     **/
    public function nest_with_leading_and_trailing_separator_does_not_produce_children()
    {
        $array = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            '.id.'          => 578,
            'address.street'=> 'Elmstreet 13'
        ];

        $nested = Map::nest($array);

        $resultArray = [
            'id'            => 13,
            'name'          => 'Michael',
            'surname'       => 'Tils',
            '.id.'          => 578,
            'address'       => [
                'street' => 'Elmstreet 13'
            ]
        ];

        $this->assertEquals($resultArray, $nested);
    }

    /**
     * @test
     **/
    public function nest_with_three_levels()
    {
        $array = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'category.type.name'    => 'job'
        ];

        $categoryArray = [
            'id'     => 27,
            'name'   => 'worker'
        ];

        $nested = Map::nest($array);

        $this->assertEquals($categoryArray, $nested['category']['parent']);
    }

    /**
     * @test
     **/
    public function flatten_with_three_levels()
    {
        $array = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'category.type.name'    => 'job'
        ];

        $nested = Map::nest($array);

        $this->assertEquals($array, Map::flatten($nested));
    }

    /**
     * @test
     **/
    public function nest_removes_direct_leaf()
    {
        $array = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'category.type.name'    => 'job'
        ];

        $invalidArray = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address'               => 'foo',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'category.type.name'    => 'job'
        ];

        $nested = Map::nest($array);
        $nestedFromInvalid = Map::nest($invalidArray);

        $this->assertEquals($nested, $nestedFromInvalid);
    }

    /**
     * @test
     **/
    public function get_with_separator_returns_root_nodes()
    {
        $array = Map::nest([
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'age'                   => 86,
            'tags'                  => ['one', 'two']
        ]);


        $root = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'age'                   => 86,
            'tags'                  => ['one', 'two']
        ];

        $this->assertEquals($root, Map::get($array, '.'));

    }

    /**
     * @test
     **/
    public function get_with_leading_separator_returns_root_of_child()
    {
        $array = Map::nest([
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.id'           => 83,
            'category.name'         => 'delivery',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'age'                   => 86,
        ]);

        $categoryRoot = [
            'id'                    => 83,
            'name'                  => 'delivery'
        ];

        $this->assertEquals($categoryRoot, Map::get($array, 'category.'));
    }

    /**
     * @test
     **/
    public function nest_with_different_separator()
    {
        $array = [
            'id'                       => 13,
            'name'                     => 'Michael',
            'surname'                  => 'Tils',
            'address__id'              => 578,
            'address__street'          => 'Elmstreet 13',
            'category__parent__id'     => 27,
            'category__parent__name'   => 'worker',
            'category__type__name'     => 'job'
        ];

        $addressArray = [
            'id'       => 578,
            'street'   => 'Elmstreet 13'
        ];

        $categoryArray = [
            'id'     => 27,
            'name'   => 'worker'
        ];

        $typeArray = [
            'name'     => 'job'
        ];

        $nested = Map::nest($array, '__');

        $this->assertEquals($addressArray, $nested['address']);
        $this->assertEquals($categoryArray, $nested['category']['parent']);
        $this->assertEquals($typeArray, $nested['category']['type']);
    }

    /**
     * @test
     **/
    public function get_with_three_levels()
    {
        $array = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'category.type.name'    => 'job'
        ];

        $categoryArray = [
            'id'     => 27,
            'name'   => 'worker'
        ];

        $nested = Map::nest($array);

        $this->assertEquals($categoryArray, Map::get($nested, 'category.parent'));
        $this->assertEquals($array['category.type.name'], Map::get($nested, 'category.type.name'));
        $this->assertNull(Map::get($nested, 'category.owner.name'));
    }

    /**
     * @test
     **/
    public function get_with_asterisk_returns_complete_array()
    {
        $array = [
            'id'                    => 13,
            'name'                  => 'Michael',
            'surname'               => 'Tils',
            'address.id'            => 578,
            'address.street'        => 'Elmstreet 13',
            'category.parent.id'    => 27,
            'category.parent.name'  => 'worker',
            'category.type.name'    => 'job'
        ];

        $nested = Map::nest($array);
        $this->assertEquals($nested, Map::get($nested, '*'));
    }


}