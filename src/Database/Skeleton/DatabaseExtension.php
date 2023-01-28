<?php
/**
 *  * Created by mtils on 14.01.2023 at 12:26.
 **/

namespace Koansu\Database\Skeleton;

use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Database\Factories\MySQLFactory;
use Koansu\Database\Factories\SQLiteFactory;
use Koansu\Skeleton\AppExtension;

class DatabaseExtension extends AppExtension
{
    public function bind(): void
    {
        $this->app->share(DatabaseConnectionFactory::class, function () {
            return $this->makeConnectionFactory();
        });
    }

    protected function makeConnectionFactory() : DatabaseConnectionFactory
    {
        /** @var DatabaseConnectionFactory $factory */
        $factory = $this->app->create(DatabaseConnectionFactory::class);

        $factory->extend('sqlite', new SQLiteFactory());
        $factory->extend('mysql', new MySQLFactory());

        if (!$config = $this->app->config('database')) {
            return $factory;
        }

        $factory->configure($config['connections'], $config['connection']);


        return $factory;
    }

}