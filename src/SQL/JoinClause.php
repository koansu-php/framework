<?php
/**
 *  * Created by mtils on 24.12.2022 at 07:04.
 **/

namespace Koansu\SQL;

use Closure;
use Koansu\Core\Str;
use Koansu\SQL\Query;

use function func_get_args;
use function is_string;

/**
 * Class JoinClause
 *
 * @property      string|Str    table
 * @property      string        alias
 * @property      string        direction (LEFT|RIGHT|FULL)
 * @property      string        unification (INNER|OUTER|CROSS)
 * @property-read Parentheses   conditions
 * @property-read string        id Either the table or its alias
 */
class JoinClause
{
    /**
     * @var Query
     */
    protected $query;

    /**
     * @var string|Str
     */
    protected $table = '';

    /**
     * @var string
     */
    protected $alias = '';

    /**
     * @var string
     */
    protected $direction = '';

    /**
     * @var string
     */
    protected $unification = '';

    /**
     * @var Parentheses
     */
    protected $conditions;

    /**
     * JoinClause constructor.
     *
     * @param string        $table (optional)
     * @param Query|null    $query (optional)
     */
    public function __construct(string $table = '', Query $query = null)
    {
        $this->table = $table;
        $this->query = $query;
        $this->conditions = new Parentheses('AND');
    }

    /**
     * Set the on condition(s).
     *
     * @param string|Predicate $left
     * @param string|Str       $operatorOrRight
     * @param string|Str       $right
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function on($left, $operatorOrRight = '', $right = '') : JoinClause
    {
        if ($left instanceof Predicate) {
            $this->conditions->where($left);
            return $this;
        }
        $predicate = new Predicate(...func_get_args());
        $this->conditions->where($predicate->rightIsKey(true));
        return $this;
    }

    /**
     * Append a new braced group of expressions to the on clause.
     * Either use a callable to add your expressions or use the return value.
     *
     * @param string   $boolean (and|or)
     * @param ?callable $builder (optional)
     *
     * @return Parentheses
     *
     * @see Parentheses::__invoke()
     */
    public function __invoke(string $boolean = 'AND', callable $builder = null) : Parentheses
    {

        $countBefore = count($this->conditions);
        $group = $this->conditions->__invoke($boolean, $builder);

        if ($countBefore !== 0) {
            return $group;
        }
        // There were no condition before this call
        // So we assume the on() method was not called and only
        // $clause('AND', f()) was called
        $first = $group->first();

        if (!$first instanceof Predicate) {
            return $group;
        }

        // ...and if the right operand is just a string it's probably a column
        if (is_string($first->right)) {
            $first->rightIsKey();
        }

        return $group;
    }

    /**
     * Set an alias for the table.
     *
     * @param string $alias
     *
     * @return $this
     */
    public function as(string $alias) : JoinClause
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * Make the join a left join.
     *
     * @return $this
     */
    public function left() : JoinClause
    {
        $this->direction = 'LEFT';
        return $this;
    }

    /**
     * Make the join a right.
     *
     * @return $this
     */
    public function right() : JoinClause
    {
        $this->direction = 'RIGHT';
        return $this;
    }

    /**
     * Make the join a full join.
     *
     * @return $this
     */
    public function full() : JoinClause
    {
        $this->direction = 'FULL';
        return $this;
    }

    /**
     * Make it an inner join.
     *
     * @return $this
     */
    public function inner() : JoinClause
    {
        $this->unification = 'INNER';
        return $this;
    }

    /**
     * Make it an outer join.
     *
     * @return $this
     */
    public function outer() : JoinClause
    {
        $this->unification = 'OUTER';
        return $this;
    }

    /**
     * Make it a cross join.
     *
     * @return $this
     */
    public function cross() : JoinClause
    {
        $this->unification = 'CROSS';
        return $this;
    }

    public function __get(string $name)
    {
        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        switch ($name) {
            case 'table':
                return $this->table;
            case 'alias':
                return $this->alias;
            case 'direction':
                return $this->direction;
            case 'unification':
                return $this->unification;
            case 'conditions':
                return $this->conditions;
            case 'id':
                return $this->alias ? (string)$this->alias : (string)$this->table;
        }
        return null;
    }

    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'table':
                $this->table = $value;
                return;
            case 'alias':
                $this->alias = $value;
                return;
            case 'direction':
                $this->direction = $value;
                return;
            case 'unification':
                $this->unification = $value;
                return;
        }
    }

    // The following method are just to allow a fluid syntax if you work with
    // $query->join

    /**
     * Perform the select call on the passed query.
     *
     * @param string[]|Str[] ...$columns
     *
     * @return Query
     */
    public function select(...$columns)
    {
        return $this->query->select(...$columns);
    }

    /**
     * Set the table on the passed query.
     *
     * @param string|Str $table
     *
     * @return Query
     * @noinspection PhpMissingParamTypeInspection
     */
    public function from($table)
    {
        return $this->query->from($table);
    }

    /**
     * Perform a join call on the passed query.
     *
     * @param string|Str|JoinClause $table
     *
     * @return JoinClause
     * @noinspection PhpMissingParamTypeInspection
     */
    public function join($table) : JoinClause
    {
        return $this->query->join($table);
    }

    /**
     * Call where on the passed query.
     *
     * @param string|Str|Closure $operand
     * @param mixed $operatorOrValue (optional)
     * @param mixed $value (optional)
     *
     * @return Query
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function where($operand, $operatorOrValue = null, $value = null)
    {
        return $this->query->where(...func_get_args());
    }

    /**
     * Call groupBy on the passed query.
     *
     * @param mixed ...$column
     *
     * @return Query
     */
    public function groupBy(...$column)
    {
        return $this->query->groupBy(...$column);
    }

    /**
     * Call orderBy on the passed query
     *
     * @param string|Str|array $column
     * @param string           $direction (default:ASC)
     *
     * @return Query
     * @noinspection PhpMissingParamTypeInspection
     */
    public function orderBy($column, string $direction = 'ASC')
    {
        return $this->query->orderBy($column, $direction);
    }

    /**
     * Call having() on the passed query.
     *
     * @param string|Str|Closure    $operand
     * @param mixed                 $operatorOrValue (optional)
     * @param mixed                 $value (optional)
     *
     * @return Query
     * @noinspection PhpMissingParamTypeInspection
     */
    public function having($operand, $operatorOrValue = null, $value = null)
    {
        return $this->query->having(...func_get_args());
    }

    /**
     * Call offset() on the passed query.
     *
     * @param int|string|Str|null $offset
     * @param int|string|Str      $limit (optional)
     *
     * @return Query
     * @noinspection PhpMissingParamTypeInspection
     */
    public function offset($offset, $limit = null)
    {
        return $this->query->offset(...func_get_args());
    }

    /**
     * Call limit() on the passed query.
     *
     * @param int|string|Str|null $offset
     * @param int|string|Str      $limit (optional)
     *
     * @return Query
     */
    public function limit($limit, $offset = null)
    {
        return $this->query->limit(...func_get_args());
    }

    /**
     * Call values() on the passed query.
     *
     * @param string|array $key
     * @param mixed        $value (optional)
     *
     * @return Query
     * @noinspection PhpMissingParamTypeInspection
     */
    public function values($key, $value = null)
    {
        return $this->query->values(...func_get_args());
    }
}