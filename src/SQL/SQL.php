<?php
/**
 *  * Created by mtils on 21.12.2022 at 21:29.
 **/

namespace Koansu\SQL;

use Closure;
use DateTime;
use Koansu\Core\Contracts\SelfRenderable;
use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\Core\Str;
use Koansu\SQL\Contracts\Dialect;
use Koansu\SQL\Dialects\MySQLDialect;
use Koansu\SQL\Dialects\SQLiteDialect;

use function array_map;
use function func_num_args;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_replace;
use function str_replace;

class SQL
{
    const MY = 'mysql';

    const POSTGRES = 'postgresql';

    const SQLITE = 'sqlite';

    const MS = 'mssql';

    /**
     * @var Dialect[]
     */
    private static $dialects = [];

    /**
     * @var string
     */
    private static $defaultDialect = self::SQLITE;

    /**
     * Try to build a readable sql query of a prepared one.
     *
     * @param string|Query  $query
     * @param array         $bindings
     * @param string        $quoteChar (default:')
     *
     * @return string
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function render($query, array $bindings=[], $quoteChar="'") : string
    {

        if ($query instanceof Query) {
            $expression = self::renderQuery($query);
            return self::render($expression->__toString(), $expression->getBindings());
        }

        if (!$bindings) {
            return "$query";
        }

        $keys = [];
        $values = [];

        # build a regular expression for each parameter
        foreach ($bindings as $key=>$value) {
            $keys[] = is_string($key) ? '/:'.$key.'/' : '/[?]/';
            if ($value instanceof DateTime) {
                $values[] = $value->format('Y-m-d H:i:s');
                continue;
            }
            $values[] = is_numeric($value) ? (int)$value : "$quoteChar$value$quoteChar";
        }

        $count=0;

        return preg_replace($keys, $values, "$query", 1, $count);

    }

    /**
     * @param Query             $query
     * @param string|Dialect    $dialect (default: sqlite)
     *
     * @return SQLExpression
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function renderQuery(Query $query, $dialect='') : SQLExpression
    {
        $renderer = null;

        if (!$dialect && $query instanceof SelfRenderable) {
            $renderer = $query->getRenderer();
        }

        if (!$renderer) {
            $renderer = new QueryRenderer(self::dialect($dialect ?: self::SQLITE));
        }

        return $renderer->render($query);

    }

    /**
     * A shortcut to create a KeyExpression
     *
     * @param string $name
     * @param string $alias (optional)
     *
     * @return Column
     **/
    public static function key(string $name, string $alias='') : Column
    {
        return new Column($name, $alias);
    }
// Later...
//     public static function func($name, $parameters)
//     {
//         return

//     }

    /**
     * Create a new ConditionGroup.
     *
     * @param string|Str|Closure $operand
     * @param mixed              $operatorOrValue (optional)
     * @param mixed              $value (optional)
     *
     * @return Parentheses
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function where($operand, $operatorOrValue=null, $value=null) : Parentheses
    {
        $g = new Parentheses();

        if (func_num_args() == 1) {
            return $g->where($operand);
        }

        if (func_num_args() == 2) {
            return $g->where($operand, $operatorOrValue);
        }

        return $g->where($operand, $operatorOrValue, $value);

    }

    /**
     * Return a raw expression.
     *
     * @param string $string
     * @param array $bindings (optional)
     *
     * @return SQLExpression
     */
    public static function raw(string $string, array $bindings=[]) : SQLExpression
    {
        return new SQLExpression($string, $bindings);
    }

    /**
     * Create a sql dialect out of a string or just return the dialect if it
     * is already a dialect, and we don't have an extension that overwrites it.
     *
     * @param string|Dialect $dialect
     *
     * @return Dialect
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function dialect($dialect) : Dialect
    {
        $dialectName = "$dialect";

        // Event if this would be a dialect already check for an extension
        if (isset(self::$dialects[$dialectName])) {
            return self::$dialects[$dialectName];
        }

        if ($dialect instanceof Dialect) {
            return $dialect;
        }

        if ($dialectName == static::SQLITE) {
            return new SQLiteDialect();
        }

        if ($dialectName == static::MY) {
            return new MySQLDialect();
        }

        throw new HandlerNotFoundException("SQL dialect $dialectName is not supported");
    }

    public static function setDialect(Dialect $dialect, string $name='') : void
    {
        static::$dialects[$name ?: $dialect->__toString()] = $dialect;
    }

    public static function hasDialect(string $name) : bool
    {
        return in_array($name, [self::SQLITE, self::MY]) || isset(static::$dialects[$name]);
    }

    public static function defaultDialect() : string
    {
        return self::$defaultDialect;
    }

    public static function setDefaultDialect(string $dialect) : void
    {
        self::$defaultDialect = $dialect;
    }

    /**
     * Create a string to render an insert statement.
     *
     * @param array             $values
     * @param Dialect|string    $dialect (optional)
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function renderColumnsForInsert(array $values, $dialect='') : string
    {
        // Not used in EMS
        $columns = [];
        $quotedValues = [];
        $dialect = self::dialectOrFail($dialect);

        foreach ($values as $column=>$value) {
            $columns[] = $dialect->quote($column, Dialect::NAME);
            $quotedValues[] = $dialect->quote($value);
        }

        return '(' . implode(",", $columns) . ")\nVALUES (" . implode(",", $quotedValues) . ')';
    }

    /**
     * Create a string to render an update (without bindings)
     *
     * @param array   $values
     * @param Dialect|string $dialect (optional)
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function renderColumnsForUpdate(array $values, $dialect='') : string
    {
        // Used in Database\Storages\StorageDriver but its just pass through
        return static::renderKeyValue($values, ",\n", $dialect);
    }

    /**
     * Make an assoc array to a where string.
     *
     * @param array         $values
     * @param string        $boolean (default: AND)
     * @param Dialect|string $dialect (optional)
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function renderColumnsForWhere(array $values, string $boolean='AND', $dialect='') : string
    {
        // Used in Database\Storages\StorageDriver and KeyValueStorage
        return static::renderKeyValue($values, " $boolean\n", $dialect);
    }

    /**
     * Render columns in a `$key` = "$value", `$key` = "$value" form.
     *
     * @param array             $values
     * @param string            $connectBy (default: ,\n)
     * @param Dialect|string    $dialect (optional)
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function renderKeyValue(array $values, string $connectBy=",\n", $dialect='') : string
    {
        $lines = [];
        $dialect = self::dialectOrFail($dialect);

        foreach ($values as $column=>$value) {

            if ($value instanceof Str) {
                $lines[] = $dialect->quote($column, Dialect::NAME) . " = $value";
                continue;
            }

            if (!is_array($value)) {
                $lines[] = $dialect->quote($column, Dialect::NAME) . ' = ' . $dialect->quote($value);
                continue;
            }

            $quotedValues = array_map(function ($item) use ($dialect) {
                return $dialect->quote($item);
            }, $value);

            $lines[] = $dialect->quote($column, Dialect::NAME) . ' IN (' . implode(", ",$quotedValues) . ')';
        }

        return implode($connectBy, $lines);
    }

    /**
     * Escape a string to search in a like query. Translates asterisk (*) to
     * percent (%) and safes the original % signs.
     *
     * @param string $criterion
     * @param string $wildcard (default:*)
     * @return string
     */
    public static function wildcardToLike(string $criterion, string $wildcard='*') : string
    {
        $level1 = str_replace('%', '<|>', $criterion);
        $level2 = str_replace($wildcard, '%', $level1);
        return str_replace('<|>', '\%', $level2);
    }

    private static function dialectOrFail($dialect) : Dialect
    {
        if ($dialect instanceof Dialect) {
            return $dialect;
        }
        return self::dialect($dialect ?: self::defaultDialect());
    }
}