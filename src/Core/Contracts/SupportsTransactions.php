<?php
/**
 *  * Created by mtils on 24.12.2022 at 13:30.
 **/

namespace Koansu\Core\Contracts;

interface SupportsTransactions
{
    /**
     * Starts a new transaction.
     *
     * @return bool
     **/
    public function begin() : bool;

    /**
     * Commits the last transaction.
     *
     * @return bool
     **/
    public function commit() : bool;

    /**
     * Revert the changes of last transaction.
     *
     * @return bool
     **/
    public function rollback() : bool;

    /**
     * Run the callable in a transaction.
     * begin(); $run(); commit();
     *
     * @param callable $run
     * @param int      $attempts (default:1)
     *
     * @return mixed The result of the callable
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function transaction(callable $run, int $attempts=1);

    /**
     * Return if a transaction is currently running.
     *
     * @return bool
     **/
    public function isInTransaction() : bool;
}