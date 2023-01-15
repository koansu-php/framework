<?php
/**
 *  * Created by mtils on 31.12.2022 at 07:08.
 **/

namespace Koansu\Database;

use Closure;
use Koansu\Core\ConfigurableTrait;
use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\Core\Contracts\SupportsTransactions;
use Koansu\Core\HookableTrait;
use Koansu\Core\Str;
use Koansu\Database\Contracts\DatabaseConnection;
use Koansu\Core\Type;
use Koansu\Core\Url;
use Koansu\Database\Contracts\Prepared;
use Koansu\Database\Exceptions\DatabaseNameNotFoundException;
use Koansu\Database\Exceptions\DatabaseSyntaxException;
use Koansu\SQL\Contracts\Dialect;
use Koansu\Database\Exceptions\DatabaseException;
use Koansu\Database\Exceptions\DatabaseLockException;
use Exception;
use InvalidArgumentException;
use Koansu\SQL\Query as BaseQuery;
use Koansu\SQL\QueryRenderer;
use OverflowException;
use PDO;
use PDOException;
use PDOStatement;

use function call_user_func;
use function implode;
use function in_array;
use function is_callable;
use function is_int;
use function is_object;
use function microtime;
use function round;
use function trim;

class PDOConnection implements DatabaseConnection, SupportsTransactions, Configurable, HasMethodHooks
{
    use ConfigurableTrait;
    use HookableTrait;

    /**
     * @var int
     **/
    const KEY_CASE = PDO::ATTR_CASE;

    /**
     * @var int
     **/
    const NULLS = PDO::ATTR_ORACLE_NULLS;

    /**
     * @var int
     **/
    const STRINGIFY_NUMBERS = PDO::ATTR_STRINGIFY_FETCHES;

    // const ATTR_STATEMENT_CLASS Not supported right now

    /**
     * @var int
     **/
    const TIMEOUT = PDO::ATTR_TIMEOUT;

    // const AUTOCOMMIT = PDO::ATTR_AUTOCOMMIT; / Not supported by all drivers

    // const ATTR_EMULATE_PREPARES Will perhaps never be supported

    /**
     * @var int
     **/
    const BUFFERED_QUERIES = PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;

    /**
     * @var int
     **/
    const FETCH_MODE = PDO::ATTR_DEFAULT_FETCH_MODE;

    /**
     * @var string
     **/
    const RETURN_LAST_ID = 'RETURN_LAST_ID';

    /**
     * @var string
     **/
    const RETURN_LAST_AFFECTED = 'RETURN_LAST_AFFECTED';

    /**
     * Set this option to greater than 0 to auto reconnect when the connection
     * appears to be dropped by the database server or network in between.
     *
     * @var string
     */
    const AUTO_RECONNECT_TRIES = 'AUTO_RECONNECT_TRIES';

    /**
     * @var PDO
     **/
    protected $resource;

    /**
     * @var PDO[]
     **/
    protected $resources=[];

    /**
     * @var int
     */
    protected $resourceIndex = -1;

    /**
     * @var Url
     **/
    protected $url;

    /**
     * @var string
     */
    protected $dsn = '';

    /**
     * @var string|object
     **/
    protected $dialect;

    /**
     * @var Closure
     **/
    protected $errorHandler;

    /**
     * @var callable
     */
    protected $errorConverter;

    /**
     * @var array
     **/
    protected $defaultOptions = [
        self::KEY_CASE              => PDO::CASE_NATURAL,
        self::NULLS                 => PDO::NULL_NATURAL,
        self::STRINGIFY_NUMBERS     => false,
        self::TIMEOUT               => 0,
        self::FETCH_MODE            => PDO::FETCH_ASSOC,
        self::RETURN_LAST_ID        => true,
        self::RETURN_LAST_AFFECTED  => true,
        self::AUTO_RECONNECT_TRIES  => 3
    ];

    public function __construct(Url $url, string $dsn, array $options=[])
    {
        $this->url = $url;
        $this->dsn = $dsn;
        $this->createErrorHandlers();
        $this->mergeOptions($options);
    }

    public function open() : void
    {
        if (!$this->isOpen()) {
            $this->resourceIndex++;
            $resource = $this->createPDO($this->dsn, $this->url, (bool)$this->resourceIndex);
            $this->resources[$this->resourceIndex] = $resource;
        }
    }

    public function close() : void
    {
        if (isset($this->resources[$this->resourceIndex])) {
            $this->resources[$this->resourceIndex] = null;
        }
    }

    public function isOpen() : bool
    {
        return $this->resource() instanceof PDO;
    }

    /**
     * {@inheritdoc}
     *
     * @return PDO|null
     **/
    public function resource() : ?PDO
    {
        return $this->resources[$this->resourceIndex] ?? null;
    }

    /**
     * {@inheritdoc}
     *
     * @return Url
     **/
    public function url() : Url
    {
        return $this->url;
    }

    /**
     * Return the pdo dsn
     *
     * @return string
     */
    public function dsn() : string
    {
        return $this->dsn;
    }

    /**
     * @return Dialect|string
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function dialect()
    {
        if ($this->dialect) {
            return $this->dialect;
        }
        return $this->url()->scheme;
    }

    /**
     * Assign the dialect.
     *
     * @param string|object $dialect (string or object with __toString)
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setDialect($dialect) : PDOConnection
    {
        if (!Type::isStringLike($dialect)) {
            throw new InvalidArgumentException('Dialect hast to be stringlike, not ' . Type::of($dialect));
        }
        $this->dialect = $dialect;
        return $this;
    }

    /**
     * Starts a new transaction.
     *
     * @return bool
     **/
    public function begin() : bool
    {
        return $this->attempt(function () {
            return $this->pdo()->beginTransaction();
        });
    }

    /**
     * Commits the last transaction.
     *
     * @return bool
     **/
    public function commit() : bool
    {
        return $this->attempt(function () {
            return $this->pdo()->commit();
        });
    }

    public function rollback() : bool
    {
        return $this->attempt(function () {
            return $this->pdo()->rollBack();
        });
    }

    /**
     * Run the callable in a transaction.
     *
     * @param callable $run
     * @param int      $attempts (default:1)
     *
     * @return mixed The result of the callable
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function transaction(callable $run, int $attempts=1)
    {

        if ($attempts < 1) {
            throw new InvalidArgumentException("Invalid transaction attempts: $attempts");
        }

        for ($i = 1; $i <= $attempts; $i++) {

            $this->begin();

            try {
                $result = $run($this);
                $this->commit();
                return $result;
            } catch (DatabaseLockException $e) {
                $this->rollback();
                continue;
            } catch (Exception $e) {
                $this->rollback();
                throw $this->convertException($e);
            }
        }

        return false;
    }

    /**
     * Return if currently a transaction is running.
     *
     * @return bool
     **/
    public function isInTransaction() : bool
    {
        if (!$resource = $this->resource()) {
            return false;
        }
        return $resource->inTransaction();
    }

    /**
     * Run a select statement and return the result.
     *
     * @param string|Str    $query
     * @param array         $bindings (optional)
     * @param mixed         $fetchMode (optional)
     *
     * @return PDOResult
     *
     **/
    public function select($query, array $bindings=[], $fetchMode=null) : PDOResult
    {
        $fetchMode = $fetchMode === null
            ? $this->getOption(static::FETCH_MODE)
            : $fetchMode;

        $this->callBeforeListeners('select',[$query, $bindings]);

        $start = microtime(true);

        $statement = $bindings
            ? $this->prepared($query, $bindings, $fetchMode)
            : $this->selectRaw($query, $fetchMode);


        $this->callAfterListeners('select',[$query, $bindings, $statement, $this->getElapsedTime($start)]);

        return new PDOResult($statement);

    }

    public function insert($query, array $bindings=[], $returnLastInsertId=null) : ?int
    {
        $returnLastInsertId = $returnLastInsertId !== null
            ? $returnLastInsertId
            : $this->getOption(static::RETURN_LAST_ID);


        $this->callBeforeListeners('insert',[$query, $bindings]);

        $start = microtime(true);

        $bindings ? $this->runPrepared($query, $bindings)
            : $this->writeUnprepared($query);

        $this->callBeforeListeners('insert',[$query, $bindings, $this->getElapsedTime($start)]);

        return $returnLastInsertId ? $this->lastInsertId() : null;
    }

    /**
     * Run an altering statement.
     *
     * @param string|Str    $query
     * @param array         $bindings (optional)
     * @param bool|null     $returnAffected (optional)
     *
     * @return int|null (Number of affected rows)
     **/
    public function write($query, array $bindings=[], $returnAffected=null) : ?int
    {
        $returnAffected = $returnAffected !== null
            ? $returnAffected
            : $this->getOption(static::RETURN_LAST_AFFECTED);

        $this->callBeforeListeners('write',[$query, $bindings]);

        $start = microtime(true);

        if (!$bindings) {
            $rows = $this->writeUnprepared($query);
            $this->callAfterListeners('write',[$query, $bindings, $this->getElapsedTime($start)]);
            return $returnAffected ? $rows : null;
        }

        $statement = $this->runPrepared($query, $bindings);

        $this->callAfterListeners('write',[$query, $bindings, $this->getElapsedTime($start)]);

        return $returnAffected ? $statement->rowCount() : null;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $binding (optional)
     * @param bool  $returnAffected (optional)
     *
     * @return Prepared
     **/
    public function prepare($query, array $bindings=[]) : Prepared
    {
        $statement = $this->statement($query);

        $this->callBeforeListeners('prepare',[$query, $bindings]);

        $prepared = (new PDOPrepared(
            $statement,
            $query,
            $this->getOption(self::RETURN_LAST_AFFECTED),
            $this->errorHandler
        ))->bind($bindings);

        $this->callAfterListeners('prepare',[$query, $bindings, $prepared]);

        return $prepared;

    }

    /**
     * Return the last inserted id.
     *
     * @param string|null $sequence (optional)
     *
     * @return int (0 on none)
     **/
    public function lastInsertId($sequence=null) : int
    {
        return $this->pdo()->lastInsertId($sequence);
    }

    /**
     * Create a new query.
     *
     * @param string|null $table (optional)
     *
     * @return Query
     */
    public function query($table = null) : Query
    {
        $query = new Query();
        $query->setConnection($this);
        $renderer = new QueryRenderer();
        if ($this->dialect instanceof Dialect) {
            $renderer->setDialect($this->dialect);
        }
        $query->setRenderer($renderer);
        if ($table) {
            $query->from($table);
        }
        return $query;
    }

    /**
     * @return array
     **/
    public function methodHooks() : array
    {
        return ['select', 'insert', 'write', 'prepare'];
    }

    /**
     * Set the callable that converts NativeError to a matching Exception.
     *
     * @param callable $converter
     * @return void
     */
    public function convertErrorBy(callable $converter) : void
    {
        $this->errorConverter = $converter;
    }

    public static function exceptionByMessage(NativeError $error, Exception $original = null) : DatabaseException
    {
        if (Str::stringContains($error->message, ['no such table'])) {
            $e = new DatabaseNameNotFoundException($error->message, $error, 0, $original);
            $e->missingType = 'table';
            return $e;
        }

        if (Str::stringContains($error->message, ['no such column'])) {
            $e = new DatabaseNameNotFoundException($error->message, $error, 0, $original);
            $e->missingType = 'column';
            return $e;
        }

        if (Str::stringContains($error->message, ['syntax error'])) {
            return new DatabaseSyntaxException($error->message, $error, 0, $original);
        }

        return new DatabaseException('SQL Error', $error, 0, $original);
    }

    /**
     * Try to perform an operation. If it fails convert the native exception
     * into a SQLException.
     *
     * @param callable $run
     * @param string|Str $query
     *
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function attempt(callable $run, $query='')
    {
        try {
            return $run();
        } catch (Exception $e) {

            if (!$this->getOption(self::AUTO_RECONNECT_TRIES)) {
                throw $this->convertException($e, $query);
            }

            if (!NativeError::isLostConnectionError($e)) {
                throw $this->convertException($e, $query);
            }

            if (!$this->isRetry($run)) {
                return $this->attempt($this->makeRetry($run), $query);
            }

            return $this->attempt($run, $query);
        }
    }

    /**
     * @return PDO
     **/
    protected function pdo() : PDO
    {
        if (!$this->isOpen()) {
            $this->open();
        }
        return $this->resource();
    }

    /**
     * @param string|Str    $query
     * @param array         $bindings (optional)
     *
     * @return PDOStatement
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function runPrepared($query, array $bindings) : PDOStatement
    {
        return $this->attempt(function () use ($query, $bindings) {

            $statement = $this->prepared($query, $bindings);
            $statement->execute();

            return $statement;
        }, $query);

    }

    protected function writeUnprepared($query)
    {
        return $this->attempt(function () use ($query) {
            return $this->pdo()->exec("$query");
        }, $query);
    }

    protected function selectRaw($query, $fetchMode)
    {
        return $this->attempt(function () use ($query, $fetchMode) {
            return $this->pdo()->query("$query", $fetchMode);
        }, $query);

    }

    protected function createPDO(string $dsn, Url $url, $forceNew=false) : PDO
    {

        $pdo = new PDO(
            $dsn,
            $url->user ?: null,
            $url->password ?: null
        );

        foreach ($this->supportedOptions() as $option) {
            if (!$this->isClassOption($option)) {
                $pdo->setAttribute($option, $this->getOption($option));
            }
        }

        if ($forceNew) {
            $pdo->setAttribute(PDO::ATTR_PERSISTENT, false);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    protected function prepared($query, array $bindings, $fetchMode=null) : PDOStatement
    {
        $statement = $this->statement($query, $fetchMode);
        PDOPrepared::bindToStatement($statement, $bindings);
        return $statement;
    }

    /**
     * @param string|Str    $query
     * @param int|null      $fetchMode (optional)
     *
     * @return PDOStatement
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function statement($query, int $fetchMode=null) : PDOStatement
    {

        return $this->attempt(function () use ($query, $fetchMode) {

            $statement = $this->pdo()->prepare("$query");

            if ($fetchMode !== null) {
                $statement->setFetchMode($fetchMode);
            }
            return $statement;
        }, $query);

    }

    protected function convertException(Exception $e, $query='') : DatabaseException
    {
        $code = $e->getCode();
        $code = is_int($code) ? $code : 0;

        if (!$e instanceof PDOException) {
            $msg = 'Unknown exception occurred: ' . $e->getMessage();
            return new DatabaseException($msg, $query, $code, $e);
        }

        $dialect = $this->dialect();
        $error = $this->toError($e, $query);

        if ($exception = call_user_func($this->errorConverter, $error, $e)) {
            return $exception;
        }
        return static::exceptionByMessage($error, $e);

    }

    protected function toError(PDOException $p, $query='') : NativeError
    {

        $errorInfo = $p->errorInfo;

        return new NativeError([
           'query'    => $query,
           'sqlState' => $errorInfo[0] ?? 'HY000',
           'code'     => $errorInfo[1] ?? $p->getCode(),
           'message'  => $errorInfo[2] ?? $p->getMessage(
               )
        ]);

    }

    protected function createErrorHandlers()
    {
        $this->errorHandler = function (Exception $e, $query) {
            throw $this->convertException($e, $query);
        };
        $this->errorConverter = function (NativeError $error, Exception $e=null) {
            return null;
        };
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function isClassOption(string $key) : bool
    {
        return in_array($key, [self::RETURN_LAST_ID, self::RETURN_LAST_AFFECTED, self::AUTO_RECONNECT_TRIES]);
    }

    /**
     * Get the elapsed time since $start.
     *
     * @param  int    $start
     * @return float
     */
    protected function getElapsedTime($start) : float
    {
        return round((microtime(true) - (int)$start) * 1000, 2);
    }

    /**
     * @param callable $attempt
     *
     * @return callable|object
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function makeRetry(callable $attempt)
    {
        $retry = new class () {
            public $tries = 1;
            public $maxTries = 0;
            public $run;
            public $reConnector;
            public function __invoke()
            {
                $this->tries++;
                if ($this->tries > $this->maxTries) {
                    throw new OverflowException("Giving up on broken or dropped connection before trying attempt #$this->tries of max:$this->maxTries");
                }
                call_user_func($this->reConnector);
                return call_user_func($this->run);
            }
        };
        $retry->run = $attempt;
        $retry->maxTries = $this->getOption(self::AUTO_RECONNECT_TRIES);
        $retry->reConnector = function () {
            $this->close();
            $this->open();
        };
        return $retry;
    }

    /**
     * Check if the passed callable is a retry callable.
     *
     * @param callable $run
     *
     * @return bool
     */
    protected function isRetry(callable $run) : bool
    {
        if ($run instanceof Closure) {
            return false;
        }
        return is_object($run) && isset($run->tries) && isset($run->run) && is_callable($run->run);
    }
}