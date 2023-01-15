<?php
/**
 *  * Created by mtils on 14.01.2023 at 11:15.
 **/

namespace Koansu\Tests\Database\Factories;

use Koansu\Core\Url;
use Koansu\Database\Exceptions\DatabaseConstraintException;
use Koansu\Database\Exceptions\DatabaseDeniedException;
use Koansu\Database\Exceptions\DatabaseException;
use Koansu\Database\Exceptions\DatabaseIOException;
use Koansu\Database\Exceptions\DatabaseLockException;
use Koansu\Database\Exceptions\DatabaseNameNotFoundException;
use Koansu\Database\Exceptions\DatabaseSyntaxException;
use Koansu\Database\Factories\SQLiteFactory;
use Koansu\Database\PDOConnection;
use Koansu\SQL\Dialects\SQLiteDialect;
use Koansu\Tests\Database\StubConnectionTrait;
use Koansu\Tests\TestCase;

use function array_fill;
use function file_exists;
use function file_put_contents;
use function hash;
use function implode;
use function sys_get_temp_dir;
use function unlink;

class SQLiteFactoryTest extends TestCase
{
    use StubConnectionTrait;

    /**
     * @test
     */
    public function it_is_callable()
    {
        $this->assertIsCallable($this->make());
    }

    /**
     * @test
     */
    public function invoke_returns_configured_connection_by_array()
    {
        $con = $this->make()->__invoke(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->assertEquals('sqlite://memory', $con->url());
        $this->assertInstanceOf(SQLiteDialect::class, $con->dialect());
    }

    /**
     * @test
     */
    public function createException_creates_SQLNotFoundException_when_table_not_found()
    {
        $con = $this->con(false);

        try {
            $con->select('SELECT * FROM addresses');
            $this->fail('select on a non existing table should fail');
        } catch (DatabaseNameNotFoundException $e) {
            $this->assertEquals('table', $e->missingType);
        }
    }

    /**
     * @test
     */
    public function createException_creates_SQLNotFoundException_when_column_not_found()
    {

        $con = $this->con();

        try {
            $con->select('SELECT foo FROM users');
            $this->fail('select on a non existing column should fail');
        } catch (DatabaseNameNotFoundException $e) {
            $this->assertEquals('column', $e->missingType);
        }
    }

    /**
     * @test
     */
    public function createException_creates_SQLAccessDeniedException_if_database_not_writeable()
    {

        $con = $this->con(false, 'sqlite:///proc/test.db');

        try {
            $con->select('SELECT foo FROM users');

            $this->fail('select on a non existing column should fail');
        } catch (DatabaseDeniedException $e) {
            $this->assertStringContainsString('unable', $e->getMessage());

        }
    }

    /**
     * @test
     */
    public function createException_creates_SQLLockedException_if_database_is_locked()
    {

        $url = new Url('sqlite://' . sys_get_temp_dir() . '/test.db');

        $this->removeDB($url);

        $con = $this->con(true, $url);
        $con2 = $this->con(false, $url);

        try {
            $con->begin();
            $con->insert("INSERT INTO users (login) VALUES ('michael')");

            $con2->insert("INSERT INTO users (login) VALUES ('john')");

            $this->fail('Parallel writing to a sqlite file should fail');
        } catch (DatabaseLockException $e) {
            $this->assertStringContainsString('locked', $e->getMessage());
            $con->rollback();
            $this->removeDB($url);

        }
    }

    /**
     * @test
     */
    public function createException_creates_SQLIOException_when_column_not_found()
    {

        $dbFile = sys_get_temp_dir() . '/test.db';


        $url = new Url("sqlite://$dbFile");

        $this->removeDB($url);

        $garbage = implode("\n", array_fill(0, 50, hash('sha256','hello', true)));

        file_put_contents($dbFile, $garbage);

        $con = $this->con(false, $url);

        try {
            $con->select('SELECT foo FROM users');
            $this->fail('select on a non corrupt database should fail');
        } catch (DatabaseIOException $e) {

        }

        $this->removeDB($url);
    }

    /**
     * @test
     */
    public function createException_creates_SQLConstraintException_if_inserting_duplicates_in_unique_column()
    {

        $con = $this->con();

        try {
            $con->insert("INSERT INTO users ('login', 'weight') VALUES ('dieter', '250')");
            $con->insert("INSERT INTO users ('login', 'weight') VALUES ('dieter', '250')");
            $this->fail('inserting duplicate values in a unique column should fail');
        } catch (DatabaseConstraintException $e) {

        }

    }

    /**
     * @test
     */
    public function createException_creates_SQLSyntaxException_on_invalid_query()
    {

        $con = $this->con();

        try {
            $con->insert("bogus SELECT is stupid");
            $this->fail('Firing invalid queries should fail');
        } catch (DatabaseSyntaxException $e) {

        }

    }

    /**
     * @test
     */
    public function createException_creates_basic_SQLException_if_error_unknown()
    {

        $con = $this->con();

        try {
            $con->transaction(function () { throw new \PDOException('Failure!!'); });
            $this->fail('Firing invalid queries should fail');
        } catch (DatabaseException $e) {
            $this->assertEquals('Failure!!', $e->nativeMessage());
        }

    }

    protected function make() : SQLiteFactory
    {
        return new SQLiteFactory();
    }

    protected function con(bool $createTable=true, $url='sqlite://memory') : PDOConnection
    {
        $con = $this->make()->__invoke($url instanceof Url ? $url : new Url($url));
        if ($createTable) {
            $this->createTable($con);
        }
        return $con;
    }

    protected function removeDB(Url $url)
    {
        if (file_exists("$url->path")) {
            unlink("$url->path");
        }
    }
}