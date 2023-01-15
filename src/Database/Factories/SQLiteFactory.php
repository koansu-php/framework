<?php
/**
 *  * Created by mtils on 08.01.2023 at 12:53.
 **/

namespace Koansu\Database\Factories;

use Exception;
use Koansu\Core\Url;
use Koansu\Database\Exceptions\DatabaseConstraintException;
use Koansu\Database\Exceptions\DatabaseDeniedException;
use Koansu\Database\Exceptions\DatabaseExceededException;
use Koansu\Database\Exceptions\DatabaseException;
use Koansu\Database\Exceptions\DatabaseIOException;
use Koansu\Database\Exceptions\DatabaseLockException;
use Koansu\Database\NativeError;
use Koansu\Database\PDOConnection;
use Koansu\Skeleton\Application;
use Koansu\SQL\Dialects\SQLiteDialect;
use TypeError;

use function basename;
use function class_exists;
use function defined;
use function dirname;
use function is_array;
use function ltrim;

use const APP_ROOT;

class SQLiteFactory
{
    /**
     * SQLite error codes (from source)
     **/
    public const OK            = 0;   /* Successful result */
    public const ERROR         = 1;   /* SQL error or missing database */
    public const INTERNAL      = 2;   /* An internal logic error in SQLite */
    public const PERM          = 3;   /* Access permission denied */
    public const ABORT         = 4;   /* Callback routine requested an abort */
    public const BUSY          = 5;   /* The database file is locked */
    public const LOCKED        = 6;   /* A table in the database is locked */
    public const NOMEM         = 7;   /* A malloc() failed */
    public const READONLY      = 8;   /* Attempt to write a readonly database */
    public const INTERRUPT     = 9;   /* Operation terminated by sqlite_interrupt() */
    public const IOERR         = 10;   /* Some kind of disk I/O error occurred */
    public const CORRUPT       = 11;   /* The database disk image is malformed */
    public const NOTFOUND      = 12;   /* (Internal Only) Table or record not found */
    public const FULL          = 13;   /* Insertion failed because database is full */
    public const CANTOPEN      = 14;   /* Unable to open the database file */
    public const PROTOCOL      = 15;   /* Database lock protocol error */
    public const EMPTY_TABLE   = 16;   /* ORIGINAL: EMPTY (Internal Only) Database table is empty */
    public const SCHEMA        = 17;   /* The database schema changed */
    public const TOOBIG        = 18;   /* Too much data for one row of a table */
    public const CONSTRAINT    = 19;   /* Abort due to public constraint violation */
    public const MISMATCH      = 20;   /* Data type mismatch */
    public const MISUSE        = 21;   /* Library used incorrectly */
    public const NOLFS         = 22;   /* Uses OS features not supported on host */
    public const AUTH          = 23;   /* Authorization denied */
    public const NO_DB         = 26;   /* File is not a database */
    public const ROW           = 100;  /* sqlite_step() has another row ready */
    public const DONE          = 101;  /* sqlite_step() has finished executing */

    /**
     * @param Url|array $config
     * @return PDOConnection|null
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __invoke($config) : ?PDOConnection
    {
        if ($config instanceof Url) {
            return $this->createByUrl($config);
        }
        if (!is_array($config)) {
            throw new TypeError("Config must be array or instanceof " . Url::class);
        }
        if (!isset($config['driver']) || $config['driver'] != 'sqlite') {
            return null;
        }
        return $this->__invoke($this->configToUrl($config));
    }

    public function configToUrl(array $config, string $databasePath='') : Url
    {
        if ($config['database'][0] == '/') {
            return new Url("sqlite:///{$config['database']}");
        }
        if ($config['database'] == ':memory:') {
            return new Url("sqlite://memory");
        }
        $databasePath = $databasePath ?: $this->guessAppPath();
        $path = $databasePath . '/' . ltrim($config['database'], '/');
        return new Url("sqlite:///$path");
    }

    protected function createByUrl(Url $url) : ?PDOConnection
    {
        if ($url->scheme != 'sqlite') {
            return null;
        }
        $connection = new PDOConnection($url, $this->urlToDsn($url));
        $connection->setDialect(new SQLiteDialect());
        $connection->convertErrorBy(function (NativeError $error, Exception $e=null) {
            return $this->createException($error, $e);
        });
        return $connection;
    }

    /**
     * Create an exception caused by an error from a connection using this
     * dialect.
     *
     * @param NativeError $error
     * @param ?Exception  $original (optional)
     *
     * @return ?DatabaseException
     **/
    protected function createException(NativeError $error, Exception $original=null) : ?DatabaseException
    {
        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        /** @noinspection PhpSwitchCaseWithoutDefaultBranchInspection */
        switch ($error->code) {
            case self::PERM:
            case self::READONLY:
            case self::AUTH:
            case self::CANTOPEN:
                return new DatabaseDeniedException($error->message, $error, 0, $original);
//             case self::ABORT: Cannot reproduce this
            // Canceled
//                 echo "\nABORT ERROR $error->code";
//                 break;
            case self::BUSY:
            case self::LOCKED:
            case self::PROTOCOL:
            case self::ROW:
                return new DatabaseLockException($error->message, $error, 0, $original);
            case self::IOERR:
            case self::CORRUPT:
            case self::NO_DB:
                return new DatabaseIOException($error->message, $error, 0, $original);
            case self::NOMEM:
            case self::FULL:
            case self::TOOBIG:
                // Cannot reproduce this in ci
                // @codeCoverageIgnoreStart
                return new DatabaseExceededException($error->message, $error, 0, $original);
            // @codeCoverageIgnoreEnd
            case self::CONSTRAINT:
            case self::MISMATCH:
            case self::MISUSE:
                return new DatabaseConstraintException($error->message, $error, 0, $original);

        }
        return null;
    }

    protected function urlToDsn(Url $url) : string
    {
        $db = $url->path ?: '';
        return $url->host == 'memory' ? 'sqlite::memory:' : "sqlite:$db";
    }

    /**
     * @return string
     */
    private function guessAppPath() : string
    {
        // Skeleton
        if (defined('APP_ROOT')) {
            return APP_ROOT;
        }
        // Laravel
        if (isset($_ENV['APP_BASE_PATH']) && $_ENV['APP_BASE_PATH']) {
            return $_ENV['APP_BASE_PATH'];
        }
        // was booted
        if (class_exists(Application::class, false) && Application::current()) {
            return (string)Application::current()->path();
        }
        // Guess by php
        $calledScript = $_SERVER['SCRIPT_FILENAME'];
        // Assume it is an index.php in a "public" directory
        if (basename($calledScript) == 'index.php') {
            return dirname($calledScript, 2);
        }
        return dirname($calledScript);

    }
}