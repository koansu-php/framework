<?php
/**
 *  * Created by mtils on 28.01.2023 at 08:58.
 **/

namespace Koansu\Schema\Skeleton;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\Url;
use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Database\Illuminate\KoansuConnectionFactory;
use Koansu\Routing\Contracts\RouteRegistry;
use Koansu\Routing\RouteCollector;
use Koansu\Schema\Contracts\MigrationRunner;
use Koansu\Schema\Contracts\MigrationStepRepository;
use Koansu\Schema\Illuminate\IlluminateMigrationRunner;
use Koansu\Schema\Illuminate\IlluminateMigrationStepRepository;
use Koansu\Schema\Migrator;
use Koansu\Skeleton\AppExtension;
use Koansu\Schema\Contracts\Migrator as MigratorContract;

use Koansu\Testing\Debug;

use function get_class;
use function in_array;

class MigrationExtension extends AppExtension
{
    protected $defaultConfig = [
        'repository'    => 'illuminate',
        'runner'        => 'illuminate',
        'source'        => 'database://default/migrations',
        'paths'         => ['resources/database/migrations']
    ];

    public function bind() : void
    {
        parent::bind();

        if (!$this->app->has(ConnectionResolverInterface::class)) {
            $this->registerConnectionResolver();
        }

        $this->app->share(MigrationStepRepository::class, function () {
            return $this->makeRepository($this->getConfig('migrations'));
        });

        $this->app->bind(MigrationRunner::class, function () {
            return $this->makeRunner($this->getConfig('migrations'));
        });

        $this->app->share(MigratorContract::class, function () {
            $migrator = $this->app->create(Migrator::class);
            if ($migrator instanceof Configurable) {
                $this->configureHandler($migrator, $this->getConfig('migrations'));
            }
            return $migrator;
        });

    }

    protected function addRoutes(RouteRegistry $registry) : void
    {
        $registry->register(function (RouteCollector $collector) {

            $collector->command('migrate', MigrationCommand::class.'->migrate', 'Run all pending migrations')
                ->option('simulate', 'Just show the queries but do not change the database.', 't');

            $collector->command('migrate:status', MigrationCommand::class.'->status', 'List all migration steps and their state');

            $collector->command('migrate:install', MigrationCommand::class.'->install', 'Install the migration repository');

            $collector->command('migrate:rollback', MigrationCommand::class.'->rollback', 'Rollback last migrations')
                ->option('simulate', 'Just show the queries but do not change the database.', 't')
                ->option('limit=0', 'Limit the number of rolled back migrations. By default all migrations of the last batch will be rolled back.', 'l');
        });
    }

    protected function makeRepository(array $config) : MigrationStepRepository
    {
        $backend = $config['repository'];
        $source = new Url($config['source']);

        if ($backend != 'illuminate') {
            throw new ImplementationException("Unknown repository backend '$backend'");
        }

        $this->app->bind(MigrationRepositoryInterface::class, function () use ($source) {
            return $this->makeLaravelRepository($source);
        });

        /** @var IlluminateMigrationStepRepository $repository */
        $repository = $this->app->create(IlluminateMigrationStepRepository::class);

        if ($repository instanceof Configurable) {
            $this->configureHandler($repository, $config);
        }

        return $repository;

    }

    protected function makeRunner(array $config) : MigrationRunner
    {
        $backend = $config['runner'];

        if ($backend != 'illuminate') {
            throw new ImplementationException("Unknown runner backend '$backend'");
        }

        /** @var IlluminateMigrationRunner $runner */
        $runner = $this->app->create(IlluminateMigrationRunner::class);
        if ($runner instanceof Configurable) {
            $this->configureHandler($runner, $config);
        }
        $runner->createObjectsBy($this->app);
        return $runner;
    }

    protected function makeLaravelRepository(Url $source) : MigrationRepositoryInterface
    {
        if ($source->scheme != 'database') {
            throw new ImplementationException("The illuminate repository driver only supports a database.");
        }

        /** @var DatabaseMigrationRepository $migrationRepo */
        $migrationRepo = $this->app->create(DatabaseMigrationRepository::class, [
            'table' => $source->path->first()
        ]);
        $migrationRepo->setSource($source->host);

        return $migrationRepo;

    }
    protected function registerConnectionResolver()
    {
        $this->app->share(ConnectionResolverInterface::class, function () {
            /** @var KoansuConnectionFactory $factory */
            return $this->app->create(KoansuConnectionFactory::class, [
                'factory'   => $this->app->get(DatabaseConnectionFactory::class)
            ]);
        });
        $this->app->bind(KoansuConnectionFactory::class, function () {
            return $this->app->get(ConnectionResolverInterface::class);
        });
    }

    protected function configureHandler(Configurable $handler, array $config)
    {
        $options = $handler->supportedOptions();
        $source = new Url($config['source']);

        if (in_array(MigratorContract::PATHS, $options)) {
            $realPaths = [];
            foreach($config['paths'] as $path) {
                $realPaths[] = $this->absolutePath($path);
            }
            $handler->setOption(MigratorContract::PATHS, $realPaths);
        }
        if (in_array(MigratorContract::REPOSITORY_URL, $options)) {
            $handler->setOption(MigratorContract::REPOSITORY_URL, $source);
        }
    }
}