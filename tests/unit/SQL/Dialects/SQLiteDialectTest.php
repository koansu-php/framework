<?php
/**
 *  * Created by mtils on 14.01.2023 at 10:28.
 **/

namespace Koansu\Tests\SQL\Dialects;

use DateTime;
use InvalidArgumentException;
use Koansu\SQL\Contracts\Dialect;
use Koansu\SQL\Dialects\SQLiteDialect;
use Koansu\Tests\TestCase;
use stdClass;
use TypeError;

class SQLiteDialectTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceof(
            Dialect::class,
            $this->newDialect()
        );
    }

    /**
     * @test
     */
    public function quote_string()
    {
        $d = $this->newDialect();
        $this->assertEquals("'Hello'", $d->quote('Hello'));
        $this->assertEquals("'Hello you'", $d->quote('Hello you'));
        $this->assertEquals("'Hello ''you'", $d->quote('Hello \'you'));
    }

    /**
     * @test
     */
    public function quote_name()
    {
        $d = $this->newDialect();
        $this->assertEquals('"users"', $d->quote('users', 'name'));
        $this->assertEquals('"us""er"', $d->quote('us"er', 'name'));
    }

    /**
     * @test
     */
    public function quote_throws_exception_with_unknown_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newDialect()->quote('foo', 'bar');
    }

    /**
     * @test
     */
    public function name()
    {
        $this->assertEquals('sqlite', $this->newDialect()->name());
        $this->assertEquals('sqlite', (string)$this->newDialect());
    }

    /**
     * @test
     */
    public function timestampFormat()
    {
        $format = $this->newDialect()->timeStampFormat();
        $this->assertEquals('Y-m-d H:i:s', $format);
    }

    /**
     * @test
     */
    public function it_quotes_nested_keys()
    {
        $dialect = $this->newDialect();
        $this->assertEquals('"users"."address"."street"', $dialect->quote('users.address.street', Dialect::NAME));
    }

    /**
     * @test
     */
    public function it_renders_null_correctly()
    {
        $dialect = $this->newDialect();
        $this->assertEquals('NULL', $dialect->expression(null));
    }

    /**
     * @test
     **/
    public function it_renders_DateTime()
    {
        $awaited = '2022-12-24 18:43:59';
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $awaited);
        $dialect = $this->newDialect();
        $expression = $dialect->expression($date);

        $this->assertEquals('?', $expression->getRaw());
        $this->assertEquals([$awaited], $expression->getBindings());
    }

    /**
     * @test
     **/
    public function it_renders_scalars()
    {
        $dialect = $this->newDialect();

        $tests = [
            53, 'hello', 1.45
        ];



        foreach ($tests as $test) {
            $expression = $dialect->expression($test);
        }
        $this->assertEquals('?', $expression->getRaw());
        $this->assertEquals([$test], $expression->getBindings());
    }

    /**
     * @test
     **/
    public function it_throws_exception_with_unsupported_atomic()
    {
        $this->expectException(TypeError::class);
        $this->newDialect()->expression(new stdClass());
    }

    /**
     * @test
     **/
    public function it_returns_concat_expression()
    {
        $args = ['h','e','l','l','o'];
        $expression = $this->newDialect()->func('concat', $args);
        $this->assertEquals('? || ? || ? || ? || ?', $expression->getRaw());
        $this->assertEquals($args, $expression->getBindings());
    }

    protected function newDialect() : SQLiteDialect
    {
        return new SQLiteDialect();
    }

}