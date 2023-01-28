<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 *  * Created by mtils on 21.01.2023 at 10:08.
 **/

namespace Koansu\Tests\Schema\Illuminate;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Grammars\Grammar;
use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Database\Illuminate\KoansuConnectionFactory;
use Koansu\Schema\Contracts\MigrationRunner;
use Koansu\Schema\Exceptions\MigrationClassNotFoundException;
use Koansu\Schema\Illuminate\IlluminateMigrationRunner;
use Mockery\MockInterface;

class IlluminateMigrationRunnerTest extends AbstractIlluminateMigrationTest
{
    protected $migrationDirectory = 'database/schema/migration-tests';

    /**
     * @test
     */
    public function it_implements_interface()
    {
        $runner = $this->runner();
        $this->assertInstanceOf(MigrationRunner::class, $runner);
        $this->assertInstanceOf(IlluminateMigrationRunner::class, $runner);
    }

    /**
     * @test
     */
    public function it_pretends_on_simulate()
    {
        /** @var KoansuConnectionFactory|MockInterface $connections */
        $connections = $this->mock(KoansuConnectionFactory::class);
        /** @var Connection|MockInterface $connection */
        $connection = $this->mock(Connection::class);

        $runner = $this->runner($connections);
        $file = $this->migrationFile('2014_05_26_092001_anonymous_root_class.php');

        $connections->shouldReceive('getDefaultConnection')->andReturn('tests');
        $connections->shouldReceive('connection')->andReturn($connection);
        $connection->shouldReceive('listen');

        $connection->shouldReceive('pretend')->withArgs(function ($runner) {
            return $runner instanceof Closure;
        })->once();

        $runner->upgrade($file, true);
    }

    /**
     * @test
     */
    public function it_runs_within_transaction_if_supported()
    {
        /** @var KoansuConnectionFactory|MockInterface $connections */
        $connections = $this->mock(KoansuConnectionFactory::class);
        /** @var Connection|MockInterface $connection */
        $connection = $this->mock(Connection::class);
        /** @var Grammar|MockInterface $grammar */
        $grammar = $this->mock(Grammar::class);
        $runner = $this->runner($connections);
        $file = $this->migrationFile('2014_05_26_092001_anonymous_root_class.php');

        $connections->shouldReceive('getDefaultConnection')->andReturn('tests');
        $connections->shouldReceive('connection')->andReturn($connection);
        $connection->shouldReceive('listen');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $grammar->shouldReceive('supportsSchemaTransactions')->andReturn(true);

        $connection->shouldReceive('transaction')->withArgs(function ($runner) {
            return $runner instanceof Closure;
        })->once();

        $runner->upgrade($file, false);
    }

    /**
     * @test
     */
    public function it_runs_without_transaction_if_not_supported()
    {
        /** @var KoansuConnectionFactory|MockInterface $connections */
        $connections = $this->mock(KoansuConnectionFactory::class);
        /** @var Connection|MockInterface $connection */
        $connection = $this->mock(Connection::class);
        /** @var Grammar|MockInterface $grammar */
        $grammar = $this->mock(Grammar::class);
        /** @var Builder|MockInterface $schemaBuilder */
        $schemaBuilder = $this->mock(Builder::class);

        $runner = $this->runner($connections);
        $file = $this->migrationFile('2014_05_26_092001_anonymous_root_class.php');

        $connections->shouldReceive('getDefaultConnection')->andReturn('tests');
        $connections->shouldReceive('connection')->andReturn($connection);
        $connection->shouldReceive('listen');
        $connection->shouldReceive('getSchemaGrammar')->atLeast()->once()->andReturn($grammar);
        $connection->shouldReceive('getSchemaBuilder')->atLeast()->once()->andReturn($schemaBuilder);
        $grammar->shouldReceive('supportsSchemaTransactions')->andReturn(false);
        $schemaBuilder->shouldReceive('create')->once();

        $connection->shouldReceive('transaction')->never();

        $runner->upgrade($file, false);
    }

    /**
     * @test
     */
    public function it_uses_custom_migration_connection()
    {
        /** @var KoansuConnectionFactory|MockInterface $connections */
        $connections = $this->mock(KoansuConnectionFactory::class);
        /** @var Connection|MockInterface $connection */
        $connection = $this->mock(Connection::class);
        /** @var Grammar|MockInterface $grammar */
        $grammar = $this->mock(Grammar::class);
        $runner = $this->runner($connections);
        $file = $this->migrationFile('2015_05_26_092001_anonymous_migration_class.php');

        $connections->shouldReceive('getDefaultConnection')->andReturn('tests');
        $connections->shouldReceive('connection')->with('foo')->andReturn($connection);
        $connection->shouldReceive('listen');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $grammar->shouldReceive('supportsSchemaTransactions')->andReturn(true);

        $connection->shouldReceive('transaction')->withArgs(function ($runner) {
            return $runner instanceof Closure;
        })->once();

        $runner->upgrade($file, false);
    }

    /**
     * @test
     */
    public function it_supports_standard_classes()
    {
        /** @var KoansuConnectionFactory|MockInterface $connections */
        $connections = $this->mock(KoansuConnectionFactory::class);
        /** @var Connection|MockInterface $connection */
        $connection = $this->mock(Connection::class);
        /** @var Grammar|MockInterface $grammar */
        $grammar = $this->mock(Grammar::class);
        $runner = $this->runner($connections);
        $file = $this->migrationFile('2016_05_26_092001_real_class.php');

        $connections->shouldReceive('getDefaultConnection')->andReturn('tests');
        $connections->shouldReceive('connection')->andReturn($connection);
        $connection->shouldReceive('listen');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $grammar->shouldReceive('supportsSchemaTransactions')->andReturn(true);

        $connection->shouldReceive('transaction')->withArgs(function ($runner) {
            return $runner instanceof Closure;
        })->once();

        $runner->upgrade($file, false);
    }

    /**
     * @test
     */
    public function it_supports_standard_classes_with_dependencies()
    {
        /** @var KoansuConnectionFactory|MockInterface $connections */
        $connections = $this->mock(KoansuConnectionFactory::class);
        /** @var Connection|MockInterface $connection */
        $connection = $this->mock(Connection::class);
        /** @var Grammar|MockInterface $grammar */
        $grammar = $this->mock(Grammar::class);
        $runner = $this->runner($connections);

        $runner->createObjectsBy(function () {
            return new DatabaseConnectionFactory();
        });

        $file = $this->migrationFile('2016_05_26_092001_real_class_with_dependencies.php');

        $connections->shouldReceive('getDefaultConnection')->andReturn('tests');
        $connections->shouldReceive('connection')->andReturn($connection);
        $connection->shouldReceive('listen');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $grammar->shouldReceive('supportsSchemaTransactions')->andReturn(true);

        $connection->shouldReceive('transaction')->withArgs(function ($runner) {
            return $runner instanceof Closure;
        })->once();

        $runner->downgrade($file, false);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_migration_class_not_found()
    {
        $file = $this->migrationFile('2017_05_26_092001_no_class_in_file.php');
        $this->expectException(MigrationClassNotFoundException::class);
        $this->runner()->upgrade($file);
    }

    protected function runner(KoansuConnectionFactory $connectionFactory=null) : IlluminateMigrationRunner
    {
        return new IlluminateMigrationRunner($connectionFactory ?: $this->connectionResolver());
    }

}