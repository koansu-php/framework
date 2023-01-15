<?php
/**
 *  * Created by mtils on 22.12.2022 at 21:44.
 **/

namespace Koansu\SQL\Contracts;

use Exception;
use Koansu\Core\Str;
use Koansu\Database\Exceptions\DatabaseException;
use Koansu\Database\NativeError;
use Koansu\SQL\SQLExpression;

/**
 * A Dialect knows how to speak to a specific database.
 * The __toString() method has to return the name.
 **/
interface Dialect
{
    /**
     * @var string
     **/
    const STR = 'string';

    /**
     * @var string
     **/
    const NAME = 'name';

    public function __toString(): string;

    /**
     * Quote a string or table/column/database name
     *
     * @param string $string
     * @param string $type (default: string) Can be string|name
     *
     * @return string
     **/
    public function quote(string $string, string $type=self::STR) : string;

    /**
     * Translate a function into the dialect of dbms.
     *
     * @param string $name
     * @param array $args (optional)
     *
     * @return SQLExpression
     */
    public function func(string $name, array $args=[]) : SQLExpression;

    /**
     * Make a value to an SQLExpression. This is for translating null into "NULL"
     * etc.
     *
     * @param mixed $atomic
     * @return SQLExpression
     * @noinspection PhpMissingParamTypeInspection
     */
    public function expression($atomic) : SQLExpression;

    /**
     * Return the name of this dialect.
     *
     * @return string
     **/
    public function name() : string;

    /**
     * Return the timestamp format of this database.
     *
     * @return string
     **/
    public function timeStampFormat() : string;
}