<?php
/**
 *  * Created by mtils on 05.09.20 at 07:40.
 **/

namespace Koansu\Tests\Core\DataStructures;


use ArrayAccess;
use Countable;
use IteratorAggregate;
use Koansu\Core\DataStructures\ByTypeContainer;
use Koansu\Core\DataStructures\Sequence;
use Koansu\Core\DataStructures\StringList;
use Koansu\Tests\TestCase;
use stdClass;
use Traversable;

class ByTypeContainerTest extends TestCase
{

    /**
     * @test
     */
    public function it_implements_array_interfaces()
    {
        $container = $this->make();
        $this->assertInstanceOf(ArrayAccess::class, $container);
        $this->assertInstanceOf(Countable::class, $container);
        $this->assertInstanceOf(Traversable::class, $container);
    }

    /**
     * @test
     */
    public function it_has_working_array_interfaces()
    {
        $container = $this->make();
        $this->assertCount(0, $container);
        $this->assertFalse(isset($container['extension']));

        $extension = new stdClass();
        $container['extension'] = $extension;
        $this->assertCount(1, $container);
        $this->assertTrue(isset($container['extension']));
        $this->assertSame($extension, $container['extension']);

        $this->assertFalse(isset($container['extension2']));

        $extension2 = new stdClass();
        $container['extension2'] = $extension2;
        $this->assertCount(2, $container);
        $this->assertTrue(isset($container['extension2']));
        $this->assertSame($extension2, $container['extension2']);

        $extensions = [
            'extension'     => $extension,
            'extension2'    => $extension2
        ];
        $buffer = [];
        foreach($container as $key=>$value) {
            $buffer[$key] = $value;
        }
        $this->assertEquals($extensions, $buffer);

        unset($container['extension']);
        $this->assertCount(1, $container);
        $this->assertFalse(isset($container['extension']));

    }

    /**
     * @test
     */
    public function forInstanceOf_searches_by_exact_class_match()
    {
        $container = $this->make();

        $this->assertNull($container->forInstanceOf(Sequence::class));

        $extension = new stdClass();
        $container[Sequence::class] = $extension;

        $this->assertSame($extension, $container->forInstanceOf(Sequence::class));
    }

    /**
     * @test
     */
    public function forInstanceOf_searches_by_class_inheritance()
    {
        $container = $this->make();

        $this->assertNull($container->forInstanceOf(StringList::class));

        $extension = new stdClass();
        $container[Sequence::class] = $extension;

        $this->assertSame($extension, $container->forInstanceOf(StringList::class));
        $this->assertSame($extension, $container->forInstanceOf(ByTypeContainerTest_Sequence::class));
        $this->assertSame($extension, $container->forInstanceOf(Sequence::class));

    }

    /**
     * @test
     */
    public function forInstanceOf_misses_by_inverse_class_inheritance()
    {
        $container = $this->make();

        $this->assertNull($container->forInstanceOf(StringList::class));

        $extension = new stdClass();
        $container[StringList::class] = $extension;

        $this->assertNull($container->forInstanceOf(Sequence::class));
    }

    /**
     * @test
     */
    public function forInstanceOf_searches_by_interface_implementation()
    {
        $container = $this->make();

        $this->assertNull($container->forInstanceOf(ArrayAccess::class));

        $extension = new stdClass();
        $container[ArrayAccess::class] = $extension;

        $this->assertSame($extension, $container->forInstanceOf(StringList::class));
        $this->assertSame($extension, $container->forInstanceOf(ByTypeContainerTest_Sequence::class));

        $this->assertNull($container->forInstanceOf(Countable::class));

        $extension2 = new stdClass();
        $container[IteratorAggregate::class] = $extension2;
        $this->assertSame($extension2, $container->forInstanceOf(IteratorAggregate::class));

    }

    /**
     * @test
     */
    public function forInstanceOf_searches_by_extension_insertion_order()
    {
        $container = $this->make();

        $this->assertNull($container->forInstanceOf(ArrayAccess::class));

        $extension = new stdClass();
        $container[ArrayAccess::class] = $extension;

        $this->assertSame($extension, $container->forInstanceOf(StringList::class));
        $this->assertSame($extension, $container->forInstanceOf(ByTypeContainerTest_Sequence::class));
        $this->assertSame($extension, $container->forInstanceOf(StringList::class));

        $this->assertNull($container->forInstanceOf(IteratorAggregate::class));

        $extension2 = new stdClass();
        $container[ByTypeContainerTest_Interface::class] = $extension2;
        $this->assertSame($extension2, $container->forInstanceOf(ByTypeContainerTest_Interface::class));

        // This was the first assigned so this must still be true
        $this->assertSame($extension, $container->forInstanceOf(StringList::class));
        $this->assertSame($extension, $container->forInstanceOf(ByTypeContainerTest_Sequence::class));
        $this->assertSame($extension, $container->forInstanceOf(StringList::class));

        unset($container[ArrayAccess::class]);

        $this->assertSame($extension2, $container->forInstanceOf(ByTypeContainerTest_Sequence::class));
        $this->assertNull($container->forInstanceOf(StringList::class));

        // This will insert the extension behind the IteratorAggregate
        $container[ArrayAccess::class] = $extension;

        // This was the first assigned so this must still be true
        $this->assertSame($extension2, $container->forInstanceOf(ByTypeContainerTest_Sequence::class));
        $this->assertSame($extension, $container->forInstanceOf(ArrayAccess::class));
        $this->assertSame($extension2, $container->forInstanceOf(ByTypeContainerTest_Interface::class));
    }

    protected function make() : ByTypeContainer
    {
        return new ByTypeContainer();
    }
}

interface ByTypeContainerTest_Interface
{
    //
}

class ByTypeContainerTest_Sequence extends Sequence implements ByTypeContainerTest_Interface
{
    //
}