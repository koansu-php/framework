<?php
/**
 *  * Created by mtils on 08.01.2023 at 21:14.
 **/

namespace Koansu\Tests\Database;

use Koansu\Core\Url;
use Koansu\Database\Contracts\DatabaseConnection;
use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Database\Factories\MySQLFactory;
use Koansu\Database\Factories\SQLiteFactory;
use Koansu\Database\PDOConnection;

trait StubConnectionTrait
{
    protected $testTable = 'CREATE TABLE `users` (
        `id`        INTEGER PRIMARY KEY AUTOINCREMENT,
        `login`     TEXT NOT NULL UNIQUE,
        `age`       INTEGER,
        `weight`    REAL
    );';

    protected function newConnection($createTable=true, Url $url=null) : PDOConnection
    {
        $factory = $this->factory();
        $url = $url ?: new Url('sqlite://memory');
        $con =  $factory->connection($url);
        if ($createTable) {
            $this->createTable($con);
        }
        return $con;
    }

    protected function createTable(DatabaseConnection $con) : void
    {
        $con->write($this->testTable);
    }

    protected function factory() : DatabaseConnectionFactory
    {
        $factory = new DatabaseConnectionFactory();
        $factory->extend('sqlite', new SQLiteFactory());
        $factory->extend('mysql', new MySQLFactory());
        return $factory;
    }
}