<?php
/**
 *  * Created by mtils on 24.12.2022 at 13:26.
 **/

namespace Koansu\Database\Contracts;

use Koansu\Core\Contracts\Result;
use Koansu\Core\Str;

interface Prepared extends Result
{
    /**
     * Return the original query (string)
     *
     * @return string|Str
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function query();

    /**
     * Change all bindings of the statement. (Before fetching again)
     *
     * @param array $bindings
     *
     * @return self
     **/
    public function bind(array $bindings) : Prepared;

    /**
     * Perform an altering query with the passed binding. Return the number
     * of affected rows id $returnAffectedRows is true.
     *
     * @param ?array $bindings (optional)
     * @param bool  $returnAffectedRows (optional)
     *
     * @return int|null (null if no affected rows should be returned)
     **/
    public function write(array $bindings=null, bool $returnAffectedRows=null) : ?int;
}