<?php
/**
 *  * Created by mtils on 21.01.2023 at 08:48.
 **/

namespace Koansu\Tests\Schema\Illuminate;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Database\Illuminate\KoansuConnectionFactory;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Filesystem\LocalFilesystem;
use Koansu\Schema\Contracts\MigrationStepRepository;
use Koansu\Schema\Contracts\Migrator;
use Koansu\Schema\Exceptions\MigratorInstallationException;
use Koansu\Schema\Illuminate\IlluminateMigrationStepRepository;
use Koansu\Schema\MigrationStep;
use Koansu\Testing\Debug;
use Koansu\Tests\Database\StubConnectionTrait;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;

use function basename;

class IlluminateMigrationStepRepositoryTest extends AbstractIlluminateMigrationTest
{

    /**
     * @test
     */
    public function it_implements_interface()
    {
        $repo = $this->stepRepository();
        $this->assertInstanceOf(MigrationStepRepository::class, $repo);
        $this->assertInstanceOf(IlluminateMigrationStepRepository::class, $repo);
    }

    /**
     * @test
     */
    public function it_throws_Exception_if_repository_was_not_installed()
    {
        $repo = $this->stepRepository();
        $this->expectException(MigratorInstallationException::class);
        $repo->all();
    }

    /**
     * @test
     */
    public function all_returns_not_migrated_steps_after_installing()
    {
        $repo = $this->stepRepository();
        $repo->install();
        $files = $this->migrationFiles();
        $steps = $repo->all();
        $this->assertCount(count($files), $steps);
        foreach ($steps as $step) {
            $baseFile = basename($step->file);
            $this->assertInstanceOf(MigrationStep::class, $step);
            $this->assertEquals(0, $step->batch);
            $this->assertFalse($step->migrated);
            $this->assertTrue(isset($files[$baseFile]));
        }
    }

    /**
     * @test
     */
    public function save_saves_migration()
    {
        $repo = $this->stepRepository();
        $repo->install();
        $files = $this->migrationFiles();
        $steps = $repo->all();
        $this->assertCount(count($files), $steps);

        $steps[0]->migrated = true;
        $steps[0]->batch = 1;
        $repo->save($steps[0]);

        $steps = $repo->all();
        $this->assertEquals(1, $steps[0]->batch);
        $this->assertTrue($steps[0]->migrated);
        $this->assertEquals(0, $steps[1]->batch);
        $this->assertFalse($steps[1]->migrated);

        $steps[1]->migrated = true;
        $steps[1]->batch = 1;
        $repo->save($steps[1]);

        $steps = $repo->all();
        $this->assertEquals(1, $steps[0]->batch);
        $this->assertTrue($steps[0]->migrated);
        $this->assertEquals(1, $steps[1]->batch);
        $this->assertTrue($steps[1]->migrated);
    }

}