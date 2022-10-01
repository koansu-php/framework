<?php

namespace Koansu\Tests\Core\DataStructures;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Koansu\Core\DataStructures\StringList;

class StringListTest extends SequenceTest
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
    public function getGlue_and_setGlue()
    {
        $list = $this->newList();
        $this->assertSame($list, $list->setGlue('.'));
        $this->assertEquals('.', $list->getGlue());
    }

    /**
     * @test
     */
    public function getPrefix_and_setPrefix()
    {
        $list = $this->newList();
        $this->assertSame($list, $list->setPrefix('Fruits: '));
        $this->assertEquals('Fruits: ', $list->getPrefix());
    }

    /**
     * @test
     */
    public function getSuffix_and_setSuffix()
    {
        $list = $this->newList();
        $this->assertSame($list, $list->setSuffix(' (healthy)'));
        $this->assertEquals(' (healthy)', $list->getSuffix());
    }

    /**
     * @test
     */
    public function construct_with_string_splits()
    {
        $this->assertEquals('abcdef', (string)$this->newList('abcdef'));
    }

    /**
     * @test
     */
    public function construct_with_char_creates_range()
    {
        $CHARS = range('A', 'Z');
        $chars = range('a', 'z');
        $STRING = implode($CHARS);
        $string = implode($chars);

        $this->assertEquals($CHARS, $this->newList()->setGlue('')->setSource($STRING)->getSource());
        $this->assertEquals($chars, $this->newList()->setGlue('')->setSource($string)->getSource());

    }

    /**
     * @test
     */
    public function equals_compares_with_string()
    {
        $this->assertTrue($this->equals('foo', 'foo'));
        $this->assertTrue($this->equals('foo/bar', 'foo/bar'));
        $this->assertTrue($this->equals('foo/bar/baz', 'foo/bar/baz'));
        $this->assertTrue($this->equals('foo/bar/baz', $this->newList('foo/bar/baz')));

    }

    /**
     * @test
     */
    public function equals_compares_affixes()
    {
        $this->assertTrue($this->path('foo')->equals('foo'));
        $this->assertTrue($this->path('foo/bar')->equals('foo/bar'));
        $this->assertTrue($this->path('foo', '/')->equals('foo'));


        $this->assertTrue($this->path('foo/bar')->equals('foo/bar'));
        $this->assertTrue($this->path('foo/bar', '/')->equals('foo/bar'));
        $this->assertTrue($this->path('foo/bar', '/', '/')->equals('foo/bar'));


        $this->assertTrue($this->path('/foo/bar')->equals('foo/bar'));
        $this->assertTrue($this->path('/foo/bar', '/')->equals('foo/bar'));
        $this->assertTrue($this->path('/foo/bar', '/', '/')->equals('foo/bar'));

        $this->assertTrue($this->path('/foo/bar')->equals('foo/bar/'));
        $this->assertTrue($this->path('/foo/bar', '/')->equals('foo/bar/'));
        $this->assertTrue($this->path('/foo/bar', '/', '/')->equals('foo/bar/'));

        $this->assertTrue($this->path('foo/bar')->equals('/foo/bar'));
        $this->assertTrue($this->path('foo/bar', '/')->equals('/foo/bar'));
        $this->assertTrue($this->path('foo/bar', '/', '/')->equals('/foo/bar'));

        $this->assertTrue($this->path('/foo/bar/')->equals('/foo/bar'));
        $this->assertTrue($this->path('/foo/bar/', '/')->equals('/foo/bar'));
        $this->assertTrue($this->path('/foo/bar/', '/', '/')->equals('/foo/bar'));

        $this->assertTrue($this->path('foo/bar')->equals('/foo/bar/'));
        $this->assertTrue($this->path('foo/bar', '/')->equals('/foo/bar/'));
        $this->assertTrue($this->path('foo/bar', '/', '/')->equals('/foo/bar/'));
        $this->assertFalse($this->path('foo/bar', '/')->equals('/foo/bar/', true));

    }

    /**
     * @test
     */
    public function test_construct_with_empty_string()
    {
        $this->assertEquals([], $this->newList('')->getSource());
    }


    protected function newList($params=null)
    {
        return new StringList($params);
    }

    protected function path($string, $prefix='', $suffix='')
    {
        return new StringList($string,'/', $prefix, $suffix);
    }

    /**
     * @param string $string
     * @param mixed  $other
     * @param bool   $strict
     * @param string $glue (default:'/')
     *
     * @return bool
     */
    protected function equals($string, $other, $strict=false, $glue='/')
    {
        return $this->newList($string, $glue)->equals($other, $strict);
    }
}
