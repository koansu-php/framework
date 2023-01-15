<?php
/**
 *  * Created by mtils on 24.12.2022 at 07:29.
 **/

namespace Koansu\SQL;

use Closure;
use Koansu\Core\Contracts\Queryable;
use Koansu\Core\Str;

use function count;
use function func_get_args;
use function func_num_args;
use function is_array;

/**
 * Class Query
 *
 * The query is a value object. It is just a container to store the parts of a
 * sql query.
 *
 * Because it is a value object and not implementation specific
 * it is in Contracts namespace.
 *
 * @package Ems\Contracts\Model\Database
 *
 * @property      string[]|Str[]        columns
 * @property      string|Str            table
 * @property      string[]|Str[]        groupBys
 * @property      string[]|Str[]        orderBys
 * @property      int|string|Str        offset
 * @property      int|string|Str        limit
 * @property      bool                  distinct
 * @property      array                 values
 * @property      string                operation (SELECT|INSERT|UPDATE|DELETE|ALTER|CREATE)
 * @property-read Parentheses           havings
 * @property-read Parentheses           conditions
 * @property-read JoinClause[]          joins
 * @property-read Query[]               attachments
 */
class Query implements Queryable
{
    /**
     * @var string[]|Str[]
     */
    protected $columns = [];

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var JoinClause[]
     */
    protected $joins = [];

    /**
     * @var Parentheses
     */
    protected $conditions;

    /**
     * @var string[]
     */
    protected $groupBys = [];

    /**
     * @var array
     */
    protected $orderBys = [];

    /**
     * @var Parentheses
     */
    protected $havings;

    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @var int|null
     */
    protected $offset;

    /**
     * @var string
     */
    protected $distinct = false;

    /**
     * An associative array of values for insert or update.
     *
     * @var array
     */
    protected $values = [];

    /**
     * @var string
     */
    protected $operation = '';

    /**
     * @var Query[]
     */
    protected $attachedQueries = [];

    /**
     * Query constructor
     */
    public function __construct()
    {
        $this->conditions = new Parentheses('AND');
        $this->havings = new Parentheses('AND');
    }

    /**
     * ADD one or many select columns to the query and make it a select
     * operation.
     * To completely reset the columns use property columns access.
     *
     * @param string|string[]|Str[] ...$columns
     *
     * @return $this
     */
    public function select(...$columns) : Query
    {
        if (isset($columns[0]) && is_array($columns[0])) {
            $columns = $columns[0];
        }
        if (!$columns) {
            $this->columns = [];
            return $this;
        }
        foreach ($columns as $column) {
            $this->columns[] = $column;
        }
        return $this;
    }

    /**
     * Set the table you are querying.
     *
     * @param string|Str $table
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function from($table) : Query
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @param string|Str|JoinClause $table
     *
     * @return JoinClause
     * @noinspection PhpMissingParamTypeInspection
     */
    public function join($table) : JoinClause
    {
        $join = $table instanceof JoinClause ? $table : new JoinClause($table, $this);
        $this->joins[] = $join;
        return $join;
    }

    /**
     * {@inheritDoc}
     *
     * @param string|Str|Closure $operand
     * @param mixed $operatorOrValue (optional)
     * @param mixed $value (optional)
     *
     * @return self
     **/
    public function where($operand, $operatorOrValue = null, $value = null) : Queryable
    {
        $this->conditions->where(...func_get_args());
        return $this;
    }

    /**
     * Append a new braced group of expressions to the where clause.
     * Either use a callable to add your expressions or use the return value.
     *
     * @param string    $boolean (and|or)
     * @param ?callable $builder (optional)
     *
     * @return Parentheses
     *
     * @see Parentheses::__invoke()
     */
    public function __invoke($boolean, callable $builder = null) : Parentheses
    {
        return $this->conditions->__invoke($boolean, $builder);
    }

    /**
     * Add one or many group by columns. To completely reset the columns use
     * property access.
     *
     * @param mixed ...$column
     *
     * @return $this
     */
    public function groupBy(...$column) : Query
    {
        if (isset($column[0]) && is_array($column[0])) {
            $column = $column[0];
        }
        foreach ($column as $col) {
            $this->groupBys[] = $col;
        }
        return $this;
    }

    /**
     * Add one or many order by statements. To completely clear all order by
     * statements use property access.
     *
     * @param string|Str|array  $column
     * @param string            $direction (default:ASC)
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function orderBy($column, string $direction = 'ASC') : Query
    {

        if ($column instanceof Str) {
            $insertKey = 'expression-' . count($this->orderBys);
            return $this->orderBy([$insertKey => $column]);
        }

        if (!is_array($column)) {
            return $this->orderBy([$column => $direction]);
        }

        foreach ($column as $key => $direction) {
            $this->orderBys[$key] = $direction;
        }

        return $this;
    }

    /**
     * Same as where but for having statements (aggregated result).
     *
     * @param string|Str|Closure $operand
     * @param mixed                     $operatorOrValue (optional)
     * @param mixed                     $value (optional)
     *
     * @return self
     *
     * @see Queryable::where()
     * @noinspection PhpMissingParamTypeInspection
     */
    public function having($operand, $operatorOrValue = null, $value = null) : Query
    {
        $this->havings->where(...func_get_args());
        return $this;
    }

    /**
     * Set the offset (and limit). Reset the offset by setting it to null.
     *
     * @param int|string|Str|null $offset
     * @param int|string|Str      $limit (optional)
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function offset($offset, $limit = null) : Query
    {
        $this->offset = $offset;
        if (func_num_args() > 1) {
            $this->limit = $limit;
        }
        return $this;
    }

    /**
     * Set the limit (and offset). Reset the limit by setting it to null.
     *
     * @param int|string|Str|null $offset
     * @param int|string|Str      $limit (optional)
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function limit($limit, $offset = null)
    {
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset = $offset;
        }
        return $this;
    }

    /**
     * Make the query distinct.
     *
     *
     * @param bool $distinct
     *
     * @return $this
     */
    public function distinct(bool $distinct=true) : Query
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * Set the values for an insert, update or replace query. A passed array
     * clears the previous values, $key and $value will be added.
     *
     * @param string|array $key
     * @param mixed        $value (optional)
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function values($key, $value = null) : Query
    {
        if (!is_array($key)) {
            $this->values[$key] = $value;
            return $this;
        }
        $this->values = [];
        foreach ($key as $column => $value) {
            $this->values[$column] = $value;
        }
        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        switch ($name) {
            case 'operation':
                return $this->getOperation();
            case 'columns':
                return $this->columns;
            case 'table':
                return $this->table;
            case 'conditions':
                return $this->conditions;
            case 'values':
                return $this->values;
            case 'joins':
                return $this->joins;
            case 'groupBys':
                return $this->groupBys;
            case 'orderBys':
                return $this->orderBys;
            case 'havings':
                return $this->havings;
            case 'offset':
                return $this->offset;
            case 'limit':
                return $this->limit;
            case 'distinct':
                return $this->distinct;
            case 'attachments':
                return $this->attachedQueries;
        }

        return null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'operation':
                $this->operation = $value;
                break;
            case 'columns':
                $this->columns = [];
                $this->select(...$value);
                break;
            case 'table':
                $this->from($value);
                break;
            case 'values':
                $this->values($value);
                break;
            case 'groupBys':
                $this->groupBys = [];
                $this->groupBy(...$value);
                break;
            case 'orderBys':
                $this->orderBys = [];
                $this->orderBy($value);
                break;
            case 'offset':
                $this->offset($value);
                break;
            case 'limit':
                $this->limit($value);
                break;
            case 'distinct':
                $this->distinct($value);
                break;
        }//end switch
    }

    /**
     * Attach a query because of $reason. This is handy if one query will not be
     * enough to solve a problem. If you build queries you can attach further
     * queries which are "spin-off" of a query generator/builder. This could be
     * queries of related objects or count queries for a paginator...
     *
     * @param string $purpose
     * @param Query  $query
     *
     * @return $this
     */
    public function attach(string $purpose, Query $query) : Query
    {
        $this->attachedQueries[$purpose] = $query;
        return $this;
    }

    /**
     * Remove the attached query for $purpose.
     *
     * @param string $purpose
     *
     * @return $this
     */
    public function detach(string $purpose) : Query
    {
        if (isset($this->attachedQueries[$purpose])) {
            unset($this->attachedQueries[$purpose]);
        }
        return $this;
    }

    /**
     * Get the query that were attached with $purpose.
     *
     * @param string $purpose
     *
     * @return Query|null
     */
    public function getAttached(string $purpose) : ?Query
    {
        if (isset($this->attachedQueries[$purpose])) {
            return $this->attachedQueries[$purpose];
        }
        return null;
    }

    /**
     * Return the set operation or try to guess it.
     *
     * @return string
     */
    protected function getOperation() : string
    {

        if ($this->operation) {
            return $this->operation;
        }

        if (!$this->values) {
            return 'SELECT';
        }

        if (count($this->conditions)) {
            return 'UPDATE';
        }

        return 'INSERT';
    }

}