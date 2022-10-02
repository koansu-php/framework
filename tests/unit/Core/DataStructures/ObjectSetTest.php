<?php
/**
 *  * Created by mtils on 02.10.2022 at 07:24.
 **/

namespace Koansu\Tests\Core\DataStructures;

use Countable;
use IteratorAggregate;
use Koansu\Core\DataStructures\ObjectSet;
use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\Core\Exceptions\ItemNotFoundException;
use Koansu\Tests\TestCase;

class ObjectSetTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_container_interfaces()
    {
        $set = $this->set();
        $this->assertInstanceOf(IteratorAggregate::class, $set);
        $this->assertInstanceOf(Countable::class, $set);
    }

    /**
     * @test
     */
    public function add_adds_objects_once_by_instance()
    {
        $set = $this->set();
        $renderer = new Renderer();

        $this->assertFalse($set->contains($renderer));

        $set->add($renderer);

        $this->assertTrue($set->contains($renderer));
        $this->assertCount(1, $set);

        $set->add($renderer);
        $this->assertCount(1, $set);

        $renderer2 = new Renderer();
        $this->assertFalse($set->contains($renderer2));

        $set->add($renderer2);
        $this->assertTrue($set->contains($renderer2));
        $this->assertCount(2, $set);

        $set->add($renderer2);
        $this->assertCount(2, $set);
    }

    /**
     * @test
     */
    public function add_adds_objects_once_by_class()
    {
        $set = $this->set(true);
        $renderer = new Renderer();

        $this->assertFalse($set->contains($renderer));

        $set->add($renderer);

        $this->assertTrue($set->contains($renderer));
        $this->assertCount(1, $set);

        $renderer2 = new Renderer();
        $this->assertTrue($set->contains($renderer2));

        $set->add($renderer2);
        $this->assertCount(1, $set);

        $renderer3 = new JsonRenderer();
        $set->add($renderer3);
        $this->assertCount(2, $set);
    }

    /**
     * @test
     */
    public function add_adds_closures_once_by_instance()
    {
        $set = $this->set();

        $renderer = function ($arg='') {
            return $arg;
        };

        $renderer2 = clone $renderer;

        $this->assertFalse($set->contains($renderer));

        $set->add($renderer);

        $this->assertTrue($set->contains($renderer));
        $this->assertCount(1, $set);

        $this->assertFalse($set->contains($renderer2));

        $set->add($renderer2);
        $this->assertCount(2, $set);
        $this->assertTrue($set->contains($renderer2));

    }

    /**
     * @test
     */
    public function add_adds_closures_once_by_class()
    {
        $set = $this->set(true);

        $renderer = function ($arg='') {
            return $arg;
        };

        $renderer2 = clone $renderer;

        $this->assertFalse($set->contains($renderer));

        $set->add($renderer);

        $this->assertTrue($set->contains($renderer));
        $this->assertCount(1, $set);

        $this->assertTrue($set->contains($renderer2));

        $set->add($renderer2);
        $this->assertCount(1, $set);

    }

    /**
     * @test
     */
    public function remove_objects_by_instance()
    {
        $set = $this->set();
        $renderer = new Renderer();
        $renderer2 = new Renderer();
        $renderer3 = new Renderer();

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);

        $this->assertTrue($set->contains($renderer));
        $this->assertTrue($set->contains($renderer2));
        $this->assertTrue($set->contains($renderer3));

        $set->remove($renderer2);

        $this->assertTrue($set->contains($renderer));
        $this->assertFalse($set->contains($renderer2));
        $this->assertTrue($set->contains($renderer3));

    }

    /**
     * @test
     */
    public function remove_objects_by_class()
    {
        $set = $this->set(true);
        $this->assertTrue($set->doesCompareByClass());

        $renderer = new Renderer();
        $renderer2 = new Renderer();
        $renderer3 = new JsonRenderer();

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);

        $this->assertCount(2, $set);

        $this->assertTrue($set->contains($renderer));
        $this->assertTrue($set->contains($renderer2));
        $this->assertTrue($set->contains($renderer3));

        $set->remove($renderer2);

        $this->assertFalse($set->contains($renderer));
        $this->assertFalse($set->contains($renderer2));
        $this->assertTrue($set->contains($renderer3));

        $this->assertCount(1, $set);

    }

    /**
     * @test
     **/
    public function remove_not_existing_object_throws_exception()
    {
        $set = $this->set();
        $renderer = new Renderer();
        $this->expectException(ItemNotFoundException::class);
        $set->remove($renderer);
    }

    /**
     * @test
     */
    public function clear_removes_all_objects()
    {
        $set = $this->set();
        $renderer = new Renderer();
        $renderer2 = new Renderer();
        $renderer3 = new Renderer();

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);

        $this->assertCount(3, $set);

        $this->assertTrue($set->contains($renderer));
        $this->assertTrue($set->contains($renderer2));
        $this->assertTrue($set->contains($renderer3));

        $set->clear();
        $this->assertCount(0, $set);

        $this->assertFalse($set->contains($renderer));
        $this->assertFalse($set->contains($renderer2));
        $this->assertFalse($set->contains($renderer3));

    }

    /**
     * @test
     **/
    public function iterate_over_set()
    {
        $set = $this->set();
        $renderer = new Renderer();
        $renderer2 = new Renderer();
        $renderer3 = new Renderer();

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);

        $renderers = [];

        foreach ($set as $item) {
            $renderers[] = $item;
        }
        $this->assertSame($renderers[0], $renderer);
        $this->assertSame($renderers[1], $renderer2);
        $this->assertSame($renderers[2], $renderer3);
    }

    /**
     * @test
     */
    public function firstObjectThat_returns_handler()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain');
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf');
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $this->assertSame($renderer2, $set->firstObjectThat('supports', ['application/pdf']));
        $this->assertSame($renderer, $set->firstObjectThat('supports', ['text/plain']));
    }

    /**
     * @test
     */
    public function firstObjectThat_returns_handler_by_class()
    {
        $set = $this->set(true);
        $renderer = new Renderer();
        $renderer2 = new JsonRenderer();
        $renderer3 = new PdfRenderer();

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);

        $this->assertSame($renderer2, $set->firstObjectThat('supports', ['application/json']));
        $this->assertSame($renderer, $set->firstObjectThat('supports', ['text/plain']));
    }

    /**
     * @test
     */
    public function firstObjectThat_throws_exception_if_no_handler_found()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain');
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf');
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $this->expectException(HandlerNotFoundException::class);
        $set->firstObjectThat('supports', ['application/xml']);
    }

    /**
     * @test
     */
    public function lastObjectThat_returns_handler()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain');
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf');
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $this->assertSame($renderer3, $set->lastObjectThat('supports', ['application/pdf']));
        $this->assertSame($renderer4, $set->lastObjectThat('supports', ['text/plain']));
    }

    /**
     * @test
     */
    public function firstResultCalling_returns_first_result()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain', true);
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf', true);
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $template = 'tpl';

        $this->assertEquals($renderer2->render($template), $set->firstResultCalling('render', [$template]));
    }

    /**
     * @test
     */
    public function lastResultCalling_returns_first_result()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain', true);
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf', true);
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $template = 'tpl';

        $this->assertEquals($renderer4->render($template), $set->lastResultCalling('render', [$template]));
    }

    /**
     * @test
     */
    public function firstResultCalling_throws_exception_if_no_handler_returns_result()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain', true);
        $renderer2 = new Renderer('application/pdf', true);
        $renderer3 = new Renderer('application/pdf', true);
        $renderer4 = new Renderer('text/plain', true);

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $template = 'tpl';

        $this->expectException(HandlerNotFoundException::class);
        $set->firstResultCalling('render', [$template]);
    }

    /**
     * @test
     **/
    public function byClass_returns_class_based_set()
    {
        $set = ObjectSet::byClass();
        $this->assertTrue($set->doesCompareByClass());
    }


    /**
     * @test
     */
    public function allResultCalling_collects_all_results()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain');
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf');
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $template = 'tpl';

        $expected = [
            $renderer->render($template),
            $renderer2->render($template),
            $renderer3->render($template),
            $renderer4->render($template),
        ];

        $this->assertEquals($expected, $set->allResultsCalling('render', [$template]));
    }

    /**
     * @test
     */
    public function allResultCallingReversed_collects_all_results()
    {
        $set = $this->set();
        $renderer = new Renderer('text/plain');
        $renderer2 = new Renderer('application/pdf');
        $renderer3 = new Renderer('application/pdf');
        $renderer4 = new Renderer('text/plain');

        $set->add($renderer);
        $set->add($renderer2);
        $set->add($renderer3);
        $set->add($renderer4);

        $template = 'tpl';

        $expected = [
            $renderer4->render($template),
            $renderer3->render($template),
            $renderer2->render($template),
            $renderer->render($template)
        ];

        $this->assertEquals($expected, $set->allResultsCallingReversed('render', [$template]));
    }

    /**
     * @param bool $compareByClass (default: false)
     * @return ObjectSet
     */
    protected function set(bool $compareByClass=false) : ObjectSet
    {
        return new ObjectSet($compareByClass);
    }
}

class Renderer
{
    public $mimeType = 'text/plain';
    public $renderNull = false;

    public function __construct(string $mimeType='', $renderNull=false)
    {
        if ($mimeType) {
            $this->mimeType = $mimeType;
        }
        $this->renderNull = $renderNull;
    }

    public function supports(string $mimeType) : bool
    {
        return $mimeType == $this->mimeType;
    }

    public function render(string $template) : ?string
    {
        if ($this->renderNull) {
            return null;
        }
        return "$template in $this->mimeType";
    }
}

class JsonRenderer extends Renderer
{
    public $mimeType = 'application/json';
}
class PdfRenderer extends Renderer
{
    public $mimeType = 'application/pdf';
}