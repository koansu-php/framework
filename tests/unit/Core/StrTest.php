<?php

namespace Koansu\Tests\Core;

use Koansu\Core\Str;
use Koansu\Core\Url;
use Koansu\Tests\TestCase;

use stdClass;
use TypeError;

use UnexpectedValueException;

use function interface_exists;
use function method_exists;

/**
 * Created by mtils on 30.07.2022 at 08:17.
 **/

class StrTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        if (interface_exists('Stringable')) {
            $this->assertInstanceOf('Stringable', $this->str());
        }
        $this->assertTrue(method_exists($this->str(), '__toString'));
    }

    /**
     * @test
     */
    public function like_matches_strings()
    {
        $this->assertTrue($this->str('Hello')->isLike('Hello'));
        $this->assertTrue($this->str('Hello')->isLike('hello'));
        $this->assertTrue($this->str('Hello')->isLike('h_llo'));
        $this->assertTrue($this->str('Hello')->isLike('he%'));
        $this->assertFalse($this->str('Hello')->isLike('ell%'));
        $this->assertTrue($this->str('Hello foo my name is bar')->isLike('%my na_e is%'));
        $this->assertFalse($this->str('Hello foo my name is bar')->isLike('my na_e is%'));
        $this->assertFalse($this->str('Hello foo my name is bar')->isLike('%my na_e is'));
    }

    /**
     * @test
     **/
    public function raw_returns_raw_data()
    {
        $str = $this->str(12);
        $this->assertSame(12, $str->getRaw());
        $this->assertSame("12", $str->__toString());
    }

    /**
     * @test
     **/
    public function it_counts_chars()
    {
        $this->assertCount(5, $this->str('ABCDE'));
        $this->assertEquals(5, $this->str('ABCDE')->count());
    }

    /**
     * @test
     */
    public function get_and_set_mimetype()
    {
        $str = $this->str('*hello*')->setMimeType('text/x-markdown');
        $this->assertEquals('text/x-markdown', $str->getMimeType());
    }

    /**
     * @test
     **/
    public function use_object_as_raw()
    {
        $urlString = 'https://koansu-php.github.io';
        $url = new Url($urlString);
        $str = $this->str($url);
        $this->assertSame($url, $str->getRaw());
        $this->assertEquals($urlString, $str->__toString());

    }

    /**
     * @test
     **/
    public function assign_array_throws_exception()
    {
        $this->expectException(TypeError::class);
        $this->str([]);
    }

    /**
     * @test
     **/
    public function assign_object_without_toString_throws_exception()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->str(new stdClass());
    }


    protected function str($str='') : Str
    {
        return new Str($str);
    }
}