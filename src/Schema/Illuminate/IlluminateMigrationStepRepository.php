<?php
/**
 *  * Created by mtils on 21.01.2023 at 08:33.
 **/

namespace Koansu\Schema\Illuminate;

use Exception;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Koansu\Core\ConfigurableTrait;
use Koansu\Core\Contracts\Configurable;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Schema\Contracts\MigrationStepRepository;
use Koansu\Schema\Contracts\Migrator as MigratorContract;
use Koansu\Schema\Exceptions\MigratorException;
use Koansu\Schema\Exceptions\MigratorInstallationException;
use Koansu\Schema\MigrationStep;
use stdClass;

use function basename;

class IlluminateMigrationStepRepository implements MigrationStepRepository, Configurable
{
    use ConfigurableTrait;

    protected $defaultOptions = [
        MigratorContract::PATHS => []
    ];

    /**
     * @var MigrationRepositoryInterface
     */
    protected $nativeRepository;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var int
     */
    protected $stepLimit = 5000;

    public function __construct(MigrationRepositoryInterface $nativeRepository, Filesystem $fs)
    {
        $this->nativeRepository = $nativeRepository;
        $this->fs = $fs;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $files = $this->getMigrationFiles();
        $nativeMigrations = $this->getNativeMigrations();

        $steps = [];
        foreach ($files as $file) {

            $baseFile = $this->fs->basename($file);
            $step = new MigrationStep();
            $step->file = $file;
            if (isset($nativeMigrations[$baseFile])) {
                $step->migrated = true;
            }
            if (isset($nativeMigrations[$baseFile]->batch)) {
                $step->batch = $nativeMigrations[$baseFile]->batch;
            }
            $steps[] = $step;
        }
        return $steps;
    }

    /**
     * @param MigrationStep $step
     * @return bool
     */
    public function save(MigrationStep $step): bool
    {
        if ($step->migrated) {
            $this->nativeRepository->log(basename($step->file), $step->batch);
            return true;
        }

        $migration = new stdClass();
        $migration->migration = basename($step->file);
        $migration->batch = $step->batch;

        $this->nativeRepository->delete($migration);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function install(): void
    {
        $this->nativeRepository->createRepository();
    }

    /**
     * @return int
     */
    public function getStepLimit(): int
    {
        return $this->stepLimit;
    }

    /**
     * @param int $stepLimit
     */
    public function setStepLimit(int $stepLimit): void
    {
        $this->stepLimit = $stepLimit;
    }

    /**
     * @return string[]
     */
    protected function getMigrationFiles() : array
    {
        $files = [];
        foreach ($this->getOption(MigratorContract::PATHS) as $path) {
            foreach ($this->fs->files($path, '*_*', 'php') as $file) {
                $files[] = $file;
            }
        }
        sort($files);
        return $files;
    }

    /**
     * @return stdClass[]
     */
    protected function getNativeMigrations() : array
    {
        try {
            $migrationByFile = [];
            $migrationEntries = $this->nativeRepository->getMigrations(
                $this->stepLimit
            );
            foreach ($migrationEntries as $migrationEntry) {
                $migrationByFile[$migrationEntry->migration] = $migrationEntry;
            }
            return $migrationByFile;
        } catch (Exception $e) {
            throw $this->convertException($e);
        }
    }

    protected function convertException(Exception $e) : MigratorException
    {
        if (!$this->nativeRepository->repositoryExists()) {
            return new MigratorInstallationException(
                'Migrator backend is not installed',
                MigratorInstallationException::NOT_INSTALLED,
                $e
            );
        }
        return new MigratorException('Common migrator error', 0, $e);
    }
}