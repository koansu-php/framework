<?php
/**
 *  * Created by mtils on 24.12.2022 at 13:24.
 **/

namespace Koansu\Database\Contracts;

use Koansu\Core\Contracts\Connection;
use Koansu\Core\Contracts\Result;
use Koansu\Core\Str;
use Koansu\SQL\Contracts\Dialect;
use Koansu\Database\Query;

interface DatabaseConnection extends Connection
{
    /**
     * Return the database dialect. Something like SQLITE, MySQL,...
     *
     * @return string|Dialect (or object with __toString()
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function dialect();

    /**
     * Run a select statement and return the result.
     *
     * @param string|Str        $query
     * @param array             $bindings (optional)
     * @param mixed             $fetchMode (optional)
     *
     * @return Result
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function select($query, array $bindings = [], $fetchMode = null) : Result;

    /**
     * Run an insert statement.
     *
     * @param string|Str    $query
     * @param array         $bindings (optional)
     * @param bool          $returnLastInsertId (optional)
     *
     * @return int|null (last inserted id)
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function insert($query, array $bindings = [], bool $returnLastInsertId = null) : ?int;

    /**
     * Run an altering statement.
     *
     * @param string|Str    $query
     * @param array         $bindings (optional)
     * @param bool          $returnAffected (optional)
     *
     * @return int|null (Number of affected rows)
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function write($query, array $bindings = [], bool $returnAffected = null) : ?int;

    /**
     * Create a prepared statement.
     *
     * @param string|Str    $query
     * @param array         $bindings (optional)
     *
     * @return Prepared
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function prepare($query, array $bindings = []) : Prepared;

    /**
     * Return the last inserted id.
     *
     * @param ?string $sequence (optional)
     *
     * @return int (0 on none)
     **/
    public function lastInsertId(string $sequence = null) : int;

    /**
     * Create a new query.
     *
     * @param ?string $table (optional)
     *
     * @return Query
     */
    public function query(string $table = null) : Query;
}