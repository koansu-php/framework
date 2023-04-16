<?php
/**
 *  * Created by mtils on 28.01.2023 at 11:59.
 **/

namespace Koansu\Schema\Skeleton;

use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\Routing\ConsoleInput;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\UtilizesInput;
use Koansu\Schema\Contracts\Migrator;
use Koansu\Schema\Exceptions\MigratorInstallationException;
use Koansu\Schema\MigrationStep;
use Koansu\Skeleton\ConsoleOutput;

use function basename;
use function function_exists;
use function in_array;
use function max;
use function mb_strlen;
use function str_pad;
use function str_repeat;
use function strlen;

use const PHP_EOL;
use const STR_PAD_LEFT;

class MigrationCommand implements UtilizesInput
{
    /**
     * @var Migrator
     */
    protected $migrator;

    /**
     * @var ConsoleOutput
     */
    protected $out;

    /**
     * @var Input
     */
    protected $input;

    /**
     * @param Migrator $migrator
     * @param ConsoleOutput $out
     */
    public function __construct(Migrator $migrator, ConsoleOutput $out)
    {
        $this->migrator = $migrator;
        $this->out = $out;
    }

    /**
     * Run all pending migrations
     *
     * @param Input $input
     * @return string
     */
    public function migrate(Input $input) : string
    {
        $simulate = (bool)$input->get('simulate', false);
        $this->listenToMigratorIfSupported($this->migrator, $simulate);
        $migrated = $this->migrator->migrate(false, $simulate);
        if (!$migrated) {
            $this->line('<comment>Nothing to migrate</comment>');
            return '';
        }

        $count = count($migrated);
        if ($simulate) {
            $this->line("<comment>Simulated the run of $count Migrations:</comment>");
            $this->outputMigrations($migrated);
            $this->line("<info>No changes have been made to the database.</info>");
            return '';
        }
        $this->line("<comment>Migrated $count Migrations:</comment>");
        $this->outputMigrations($migrated);
        return '';
    }

    /**
     * Roll back the last batch of migrations.
     *
     * @param Input $input
     * @return string
     */
    public function rollback(Input $input) : string
    {
        $simulate = (bool)$input->get('simulate', false);
        $limit = (int)$input->get('limit', 0);

        $this->listenToMigratorIfSupported($this->migrator, $simulate);

        $rolledBack = $this->migrator->rollback($limit, $simulate);
        if (!$rolledBack) {
            $this->line('<error>Nothing rolled back</error>');
            return '';
        }
        $count = count($rolledBack);
        if ($simulate) {
            $this->line("<comment>Simulated the rollback of $count Migrations:</comment>");
            $this->outputMigrations($rolledBack);
            $this->line("<info>No changes have been made to the database.</info>");
            return '';
        }

        $this->line("<comment>Rolled back $count Migrations:</comment>");
        $this->outputMigrations($rolledBack);
        return '';

    }

    /**
     * Display the state of all migrations.
     *
     * @return string
     */
    public function status() : string
    {
        if (!$migrations = $this->getMigrations()) {
            $this->line('<comment>No migrations found</comment>');
            return '';
        }

        $migrated = 0;
        foreach ($migrations as $migration) {
            $migrated += (int)$migration->migrated;
        }
        $count = count($migrations);
        $tag = $count == $migrated ? 'info' : 'warning';

        $this->outputMigrations($migrations);

        $this->line("<$tag>$migrated</$tag> <info>of $count migrations were migrated.</info>");

        return '';

    }

    /**
     * Install the migration repository.
     *
     * @return string
     */
    public function install() : string
    {
        try {
            $this->migrator->migrations();
            $this->line('<error>The repository is already installed</error>');
            return '';
        } catch (MigratorInstallationException $e) {
            //
        }
        if (!$this->confirm('Are sure you want to install the repository?')) {
            $this->line('<comment>Aborted</comment>');
            return '';
        }
        $this->migrator->install();
        $this->line('<info>Migration repository created</info>');
        return '';
    }

    public function setInput(Input $input) : void
    {
        $this->input = $input;
    }


    /**
     * Output a table of migrations in console.
     *
     * @param array $migrations
     */
    protected function outputMigrations(array $migrations) : void
    {
        $longestNameLength = 0;
        foreach ($migrations as $migration) {
            $length = $this->stringLength(basename($migration->file));
            $longestNameLength = max($length, $longestNameLength);
        }

        $hl     = '+-----------' . str_repeat('-', $longestNameLength-10) . '-+-------+' ;
        $header = '| <comment>Migration</comment> ' . str_repeat(' ', $longestNameLength-10) . ' | <comment>Batch</comment> |' ;
        $this->line($hl);
        $this->line($header);
        $this->line($hl);
        foreach ($migrations as $migration) {
            $tag = $migration->migrated ? 'info' : 'mute';
            $line = "| <$tag>" . str_pad(basename($migration->file), $longestNameLength) . "</$tag> ";

            if ($migration->batch) {
                $line .= '| ' . str_pad((string)$migration->batch, 5, ' ', STR_PAD_LEFT) . ' |';
            } else {
                $line .= '|   <mute>-</mute>   |';
            }

            $this->line($line);
        }
        $this->line($hl);
    }

    /**
     * Optionally let the user install the repository
     *
     * @return bool
     */
    protected function letInstall() : bool
    {
        if (!$this->input instanceof ConsoleInput) {
            return false;
        }
        if (!$this->confirm('Want to install migration repository now?')) {
            $this->line('<warning>Aborted</warning>');
            return false;
        }
        $this->migrator->install();
        return true;
    }

    /**
     * Ask for confirmation.
     *
     * @param string $message
     * @return bool
     */
    protected function confirm(string $message) : bool
    {
        if (!$this->input instanceof ConsoleInput) {
            return true;
        }
        $message = $message[0] == '<' ? $message : "<info>$message</info>";
        $this->line($message);
        return $this->input->confirm();
    }

    /**
     * @return MigrationStep[]
     */
    protected function getMigrations() : array
    {
        try {
            return $this->migrator->migrations();
        } catch (MigratorInstallationException $e) {
            if ($e->getCode() == MigratorInstallationException::NOT_INSTALLABLE) {
                throw $e;
            }
        }
        $this->line('<error>Migration repository is not installed</error>');
        if (!$this->letInstall()) {
            return [];
        }
        return $this->getMigrations();
    }

    /**
     * @param Migrator $migrator
     * @param false $verbose
     */
    protected function listenToMigratorIfSupported(Migrator $migrator, bool $verbose=false) : void
    {
        if (!$migrator instanceof HasMethodHooks) {
            return;
        }
        if (in_array('upgrade', $migrator->methodHooks())) {
            $migrator->onBefore('upgrade', function (MigrationStep $step, $batch, $simulate) {
                $file = basename($step->file);
                $action = $simulate ? 'Simulating' : 'Starting';
                $this->line("<comment>$action migration $file in batch $batch ...</comment>");
            });
            $migrator->onAfter('upgrade', function (MigrationStep $step, $batch, $simulate) {
                $file = basename($step->file);
                $this->line("<info>Finished migration $file...</info>");
                $this->line('--------------------------------------------------------------');
            });
        }
        if (in_array('downgrade', $migrator->methodHooks())) {
            $migrator->onBefore('downgrade', function (MigrationStep $step, $simulate) {
                $file = basename($step->file);
                $action = $simulate ? 'Simulating rollback of' : 'Rolling back migration';
                $this->line("<comment>$action $file ...</comment>");
            });
            $migrator->onAfter('downgrade', function (MigrationStep $step, $simulate) {
                $file = basename($step->file);
                $this->line("<info>Finished rollback of $file...</info>");
                $this->line('--------------------------------------------------------------');
            });
        }
        if (!in_array('query', $migrator->methodHooks()) || !$verbose) {
            return;
        }
        $migrator->onAfter('query', function ($query) {
            $this->line("<mute>$query</mute>");
        });
    }

    /**
     * Output a line if we are in console environment.
     *
     * @param string $string
     * @param null $formatted
     * @param string $newLine
     */
    protected function line(string $string, $formatted=null, string $newLine=PHP_EOL) : void
    {
        if ($this->out instanceof ConsoleOutput) {
            $this->out->line($string, $formatted, $newLine);
        }
    }

    /**
     * A version of strlen that respect the installation of mb-*.
     * @param string $file
     * @return false|int
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function stringLength(string $file)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($file);
        }
        return strlen($file);
    }
}