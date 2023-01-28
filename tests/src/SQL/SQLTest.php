<?php
/**
 *  * Created by mtils on 08.01.2023 at 11:23.
 **/

namespace Koansu\Tests\SQL;

use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\SQL\Column;
use Koansu\SQL\Dialects\AbstractDialect;
use Koansu\SQL\Dialects\MySQLDialect;
use Koansu\SQL\Parentheses;
use Koansu\SQL\SQL;
use Koansu\SQL\SQLExpression;
use Koansu\Tests\TestCase;

use function strtolower;

class SQLTest extends TestCase
{
    /**
     * @test
     */
    public function render_renders_sql_statement_without_bindings()
    {
        $query = 'SELECT * FROM users WHERE id=45';
        $this->assertEquals($query, SQL::render($query));
    }

    /**
     * @test
     */
    public function render_renders_sql_statement_with_sequential_bindings()
    {
        $query = 'SELECT * FROM users WHERE id = ? and age > ?';
        $rendered = 'SELECT * FROM users WHERE id = 5 and age > 15';
        $this->assertEquals($rendered, SQL::render($query, [5,15]));
    }

    /**
     * @test
     */
    public function render_renders_sql_statement_with_named_bindings()
    {
        $query = 'SELECT * FROM users WHERE id = :id and age > :age';
        $rendered = 'SELECT * FROM users WHERE id = 5 and age > 15';
        $this->assertEquals($rendered, SQL::render($query, ['id'=>5,'age'=>15]));
    }

    /**
     * @test
     */
    public function key_returns_KeyExpression()
    {
        $e = SQL::key('foo');
        $this->assertInstanceof(Column::class, $e);
        $this->assertEquals('foo', $e);
    }

    /**
     * @test
     */
    public function where_creates_Parentheses()
    {
        $e = SQL::where(function ($e) {
            return $e->where('a', 'b');
        });

        $this->assertInstanceof(Parentheses::class, $e);

        $e = SQL::where('a', 'b');

        $this->assertInstanceof(Parentheses::class, $e);
        $this->assertEquals('a', (string)$e->expressions[0]->left);
        $this->assertEquals('=', $e->expressions[0]->operator);
        $this->assertEquals('b', $e->expressions[0]->right);

        $e = SQL::where('a', '<>', 'b');

        $this->assertInstanceof(Parentheses::class, $e);
        $this->assertEquals('a', (string)$e->expressions[0]->left);
        $this->assertEquals('<>', $e->expressions[0]->operator);
        $this->assertEquals('b', $e->expressions[0]->right);
    }

    /**
     * @test
     */
    public function raw_returns_Expression()
    {
        $e = SQL::raw('foo');
        $this->assertInstanceof(SQLExpression::class, $e);
        $this->assertEquals('foo', $e);
    }

    /**
     * @test
     */
    public function dialect_uses_custom_extension()
    {
        $test = $this->mock(AbstractDialect::class);

        SQL::setDialect($test, 'bavarian');

        $this->assertSame($test, SQL::dialect('bavarian'));
    }

    /**
     * @test
     */
    public function dialect_uses_mysql_dialect()
    {
        $this->assertInstanceOf(MySQLDialect::class, SQL::dialect('mysql'));
    }

    /**
     * @test
     */
    public function dialect_throws_Exception_if_not_supported()
    {
        $this->expectException(HandlerNotFoundException::class);
        SQL::dialect('informix');
    }
}