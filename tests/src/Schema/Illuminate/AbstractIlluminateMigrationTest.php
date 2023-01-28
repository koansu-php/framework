<?php
/**
 *  * Created by mtils on 21.01.2023 at 10:13.
 **/

namespace Koansu\Tests\Schema\Illuminate;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Database\Illuminate\KoansuConnectionFactory;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Filesystem\LocalFilesystem;
use Koansu\Schema\Contracts\Migrator;
use Koansu\Schema\Illuminate\IlluminateMigrationStepRepository;
use Koansu\Tests\Database\StubConnectionTrait;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;

abstract class AbstractIlluminateMigrationTest extends TestCase
{
    use StubConnectionTrait;
    use TestData;

    protected $migrationDirectory = 'database/schema/migrations';

    protected function stepRepository(MigrationRepositoryInterface $migrationRepository=null, Filesystem $fs=null) : IlluminateMigrationStepRepository
    {
        $repo = new IlluminateMigrationStepRepository(
            $migrationRepository ?: $this->laravelRepository(),
            $fs ?: new LocalFilesystem()
        );

        $repo->setOption(Migrator::PATHS, [
            $this->dirOfTests('database/schema/migrations')
        ]);

        return $repo;
    }

    protected function laravelRepository(ConnectionResolverInterface $resolver=null) : DatabaseMigrationRepository
    {
        return new DatabaseMigrationRepository($resolver ?: $this->connectionResolver(), 'migrations');
    }

    protected function connectionResolver(DatabaseConnectionFactory $factory=null) : ConnectionResolverInterface
    {
        return new KoansuConnectionFactory($factory ?: $this->databaseFactory());
    }

    protected function databaseFactory() : DatabaseConnectionFactory
    {
        $factory = new DatabaseConnectionFactory();
        $factory->extend('sqlite', function () {
            return $this->newConnection(false);
        });
        return $factory;
    }

    /**
     * @return string[]
     */
    protected function migrationFiles() : array
    {
        $fs = new LocalFilesystem();
        $files = [];

        foreach ($fs->files($this->dirOfTests($this->migrationDirectory), '*_*', 'php') as $file) {
            $files[$fs->basename($file)] = $file;
        }

        return $files;
    }

    protected function migrationFile(string $file) : string
    {
        return $this->dirOfTests($this->migrationDirectory) . "/$file";
    }
}