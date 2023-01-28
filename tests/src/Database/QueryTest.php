<?php
/**
 *  * Created by mtils on 14.01.2023 at 11:59.
 **/

namespace Koansu\Tests\Database;

use Koansu\Core\Contracts\Result;
use Koansu\Core\Contracts\SelfRenderable;
use Koansu\Tests\TestCase;
use Koansu\Core\Contracts\Paginatable;
use Koansu\Database\Query;
use Koansu\SQL\Query as SQLQuery;

use function str_replace;

class QueryTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interfaces()
    {
        $this->assertInstanceOf(SQLQuery::class, $this->newQuery());
        $this->assertInstanceOf(SelfRenderable::class, $this->newQuery());
        $this->assertInstanceOf(Paginatable::class, $this->newQuery());
        $this->assertInstanceOf(Result::class, $this->newQuery());
    }

    protected function newQuery() : Query
    {
        return new Query();
    }

    protected function assertSql($expected, $actual, $message='')
    {
        $expectedCmp = str_replace("\n", ' ', $expected);
        $actualCmp = str_replace("\n", ' ', $actual);
        $message = $message ?: "Expected SQL: '$expected' did not match '$actual";
        $this->assertEquals($expectedCmp, $actualCmp, $message);
    }
}