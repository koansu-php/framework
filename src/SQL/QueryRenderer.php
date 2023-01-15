<?php
/**
 *  * Created by mtils on 31.12.2022 at 07:57.
 **/

namespace Koansu\SQL;

use DateTimeInterface;
use InvalidArgumentException;
use Koansu\Core\Str;
use Koansu\Core\Type;
use Koansu\SQL\Contracts\Dialect;

use function array_push;
use function count;
use function implode;
use function in_array;
use function is_string;
use function str_replace;
use function strtolower;
use function trim;

use const PHP_EOL;

class QueryRenderer
{
    /**
     * @var Dialect
     */
    private $dialect;

    /**
     * QueryRenderer constructor.
     *
     * @param Dialect|null $dialect
     */
    public function __construct(Dialect $dialect=null)
    {
        $this->dialect = $dialect;
    }

    /**
     * Renders $item.
     *
     * @param Query $query
     *
     * @return SQLExpression
     **/
    public function __invoke(Query $query) : SQLExpression
    {
        return $this->render($query);
    }

    /**
     * Let a query renderer transform a query to a sql expression (string+bindings)
     *
     * @param Query $query
     *
     * @return SQLExpression
     */
    public function render(Query $query) : SQLExpression
    {
        $operation = $query->operation;

        if ($operation == 'SELECT') {
            return $this->renderSelect($query);
        }
        if ($operation == 'INSERT') {
            return $this->renderInsert($query, $query->values);
        }
        if ($operation == 'REPLACE') {
            return $this->renderInsert($query, $query->values, true);
        }
        if ($operation == 'UPDATE') {
            return $this->renderUpdate($query, $query->values);
        }
        if ($operation == 'DELETE') {
            return $this->renderDelete($query);
        }
        throw new InvalidArgumentException("Unsupported operation '$operation'");
    }

    /**
     * @param Query $query
     *
     * @return SQLExpression
     */
    public function renderSelect(Query $query) : SQLExpression
    {
        $bindings = [];

        $prefix = 'SELECT ' . ($query->distinct ? 'DISTINCT ': '');
        $sql = [$prefix . $this->renderColumns($query->columns)];

        $sql[] = "FROM " . $this->quote($query->table, Dialect::NAME);

        $joins = $this->renderJoins($query->joins);

        // if the join  is not empty
        if ($joinString = $joins->__toString()) {
            $sql[] = $joinString;
            $this->extend($bindings, $joins->getBindings());
        }

        if (count($query->conditions)) {
            $glue = PHP_EOL . $query->conditions->boolean . ' ';
            $wherePart = $this->renderConditionString($query->conditions, $bindings, $glue);
            $sql[] = 'WHERE ' . $wherePart;
        }

        if ($groups = $query->groupBys) {
            $groupBy = $this->renderGroupBy($groups);
            $sql[]  = 'GROUP BY ' . $groupBy->__toString();
            $this->extend($bindings, $groupBy->getBindings());
        }

        if (count($query->havings)) {
            $havingPart = $this->renderConditionString($query->havings, $bindings);
            $sql[] = 'HAVING ' . $havingPart;
        }

        if ($query->orderBys) {
            $orderBy = $this->renderOrderBy($query->orderBys);
            $sql[] = 'ORDER BY ' . $orderBy->__toString();
            $this->extend($bindings, $orderBy->getBindings());
        }

        if ($limit = $query->limit) {
            $offset = $query->offset ? ($query->offset . ', ') : '';
            $sql[] = "LIMIT $offset$limit";
        }

        $string = implode(PHP_EOL, $sql);
        return new SQLExpression($string, $this->castBindings($bindings));
    }

    /**
     * @param Query $query
     * @param array $values (optional)
     * @param bool  $replace (default: false)
     *
     * @return SQLExpression
     */
    public function renderInsert(Query $query, array $values = [], bool $replace = false) : SQLExpression
    {
        $bindings = [];
        $placeholders = [];
        $columns = [];

        $prefix = $replace ? 'REPLACE' : 'INSERT';

        $sql = ["$prefix INTO " . $this->quote($query->table, Dialect::NAME)];

        foreach ($values as $column => $value) {
            $columns[] = $this->quote($column, Dialect::NAME);

            if ($value instanceof SQLExpression) {
                $placeholders[] = $value->__toString();
                $this->extend($bindings, $value->getBindings());
                continue;
            }

            if ($value instanceof Str) {
                $placeholders[] = $value->__toString();
                continue;
            }

            $bindings[] = $value;
            $placeholders[] = '?';
        }

        $sql[] = '(' . implode(', ', $columns) . ')';
        $sql[] = 'VALUES (' . implode(', ', $placeholders) . ')';

        return new SQLExpression(implode("\n", $sql), $this->castBindings($bindings));
    }

    /**
     * @param Query $query
     * @param array $values (optional)
     *
     * @return SQLExpression
     */
    public function renderUpdate(Query $query, array $values = []) : SQLExpression
    {
        $bindings = [];
        $sql = ["UPDATE " . $this->quote($query->table, Dialect::NAME) . ' SET'];
        $assignments = [];

        foreach ($values as $column => $value) {
            $prefix = $this->quote($column, Dialect::NAME) . ' = ';

            if ($value instanceof SQLExpression) {
                $assignments[] = $prefix . $value->__toString();
                $this->extend($bindings, $value->getBindings());
                continue;
            }

            if ($value instanceof Str) {
                $assignments[] = $prefix . $value->__toString();
                continue;
            }

            $assignments[] = $prefix . "?";
            $bindings[] = $value;
        }

        $sql[] = implode(",\n", $assignments);

        if (count($query->conditions)) {
            $glue = PHP_EOL . $query->conditions->boolean . ' ';
            $wherePart = $this->renderConditionString($query->conditions, $bindings, $glue);
            $sql[] = 'WHERE ' . $wherePart;
        }

        return new SQLExpression(implode("\n", $sql), $this->castBindings($bindings));
    }

    /**
     * @param Query $query
     *
     * @return SQLExpression
     */
    public function renderDelete(Query $query) : SQLExpression
    {
        $bindings = [];
        /* @noinspection SqlWithoutWhere */
        $sql = ["DELETE FROM " . $this->quote($query->table, Dialect::NAME)];

        if (count($query->conditions)) {
            $glue = PHP_EOL . $query->conditions->boolean . ' ';
            $wherePart = $this->renderConditionString($query->conditions, $bindings, $glue);
            $sql[] = 'WHERE ' . $wherePart;
        }

        return new SQLExpression(implode("\n", $sql), $this->castBindings($bindings));
    }

    /**
     * Create a string ou of columns.
     *
     * @param iterable|string $columns
     * @param array           $bindings (optional)
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    public function renderColumns($columns = [], array &$bindings = []) : string
    {
        if (!$columns || $columns === '*' || $columns === ['*']) {
            return '*';
        }

        $strings = [];

        foreach ($columns as $column) {

            if ($column instanceof Column) {
                $string = $this->quote($column->__toString(), Dialect::NAME);
                if ($alias = $column->alias()) {
                    $string .= ' AS ' . $this->quote($alias, Dialect::STR);
                }
                $strings[] = $string;
                continue;
            }

            if ($column instanceof Str) {
                $strings[] = $this->renderExpression($column, $bindings);
                continue;
            }

            // TODO Support (and test) Query objects here too
            $strings[] = $this->quote($column, Dialect::NAME);
        }

        return implode(', ', $strings);
    }

    /**
     * @param JoinClause[] $joins
     *
     * @return SQLExpression
     */
    public function renderJoins(iterable $joins = []) : SQLExpression
    {

        $lines = [];
        $bindings = [];

        foreach ($joins as $join) {
            $line  = $join->direction ?: '';
            if ($join->unification) {
                $line .= " $join->unification";
            }

            $line .= ' JOIN ' . $this->quote($join->table, Dialect::NAME);

            if ($join->alias) {
                $line .= ' AS ' . $this->quote($join->alias, Dialect::NAME);
            }

            $lines[] = trim($line);

            if (!count($join->conditions)) {
                continue;
            }


            $lines[] = 'ON ' .  $this->renderConditionString($join->conditions, $bindings);
        }//end foreach

        return new SQLExpression(trim(implode(PHP_EOL, $lines)), $bindings);
    }

    /**
     * @param iterable $conditions
     * @param string|null $glue (default: PHP_EOL)
     *
     * @return SQLExpression
     *
     * @throws InvalidArgumentException
     */
    public function renderConditions(iterable $conditions, string $glue = null) : SQLExpression
    {
        if ($glue === null && $conditions instanceof Parentheses && $conditions->boolean) {
            $glue = PHP_EOL . trim($conditions->boolean) . ' ';
        }

        if ($glue === null) {
            $glue = PHP_EOL;
        }

        $bindings = [];
        $string = $this->renderConditionString($conditions, $bindings, $glue);

        return new SQLExpression($string, $bindings);
    }

    public function renderGroupBy(array $groupBys = []) : SQLExpression
    {
        if (!$groupBys) {
            return new SQLExpression();
        }
        $lines = [];
        $bindings = [];

        foreach ($groupBys as $expression) {
            if ($expression instanceof Str) {
                $lines[] = $this->renderExpression($expression, $bindings);
                continue;
            }
            $lines[] = $this->quote($expression, Dialect::NAME);
        }

        return new SQLExpression(implode(',', $lines), $bindings);
    }

    public function renderOrderBy(array $orderBys = []) : SQLExpression
    {
        if (!$orderBys) {
            return new SQLExpression();
        }
        $lines = [];
        $bindings = [];
        foreach ($orderBys as $key => $direction) {
            if ($direction instanceof Str) {
                $lines[] = $this->renderExpression($direction, $bindings);
                continue;
            }
            $lines[] = $this->quote($key, Dialect::NAME) . " $direction";
        }
        return new SQLExpression(implode(',', $lines), $bindings);
    }

    /**
     * Cast the bindings so that the database will accept it.
     *
     * @param array $bindings
     *
     * @return array
     */
    public function castBindings(array $bindings) : array
    {
        $casted = [];
        $dateFormat = $this->dialect ? $this->dialect->timeStampFormat() : 'Y-m-d H:i:s';
        foreach ($bindings as $key => $value) {
            if (!$value instanceof DateTimeInterface) {
                $casted[$key] = $value;
                continue;
            }
            $casted[$key] = $value->format($dateFormat);
        }
        return $casted;
    }

    /**
     * Return the assigned dialect
     *
     * @return Dialect
     */
    public function getDialect() : Dialect
    {
        return $this->dialect;
    }

    /**
     * Set the dialect to render the expression.
     *
     * @param Dialect $dialect
     *
     * @return $this
     */
    public function setDialect(Dialect $dialect) : QueryRenderer
    {
        $this->dialect = $dialect;
        return $this;
    }

    /**
     * @param iterable $conditions
     * @param array $bindings
     * @param string $glue (default: PHP_EOL)
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    protected function renderConditionString(iterable $conditions, array &$bindings = [], string $glue = PHP_EOL) : string
    {

        $lines = [];

        foreach ($conditions as $condition) {
            if ($condition instanceof Predicate) {
                $lines[] = $this->renderPredicate($condition, $bindings);
                continue;
            }

            if ($condition instanceof Str) {
                $lines[] = $this->renderExpression($condition, $bindings);
                continue;
            }

            if (Type::isStringable($condition)) {
                $lines[] = $condition;
                continue;
            }

            if (!$condition instanceof Parentheses) {
                throw new InvalidArgumentException('Unexpected condition type ' . Type::of($condition));
            }

            $prefix = '';
            if ($glue == PHP_EOL && count($lines)) {
                $prefix = 'AND ';
            }

            $lines[] = $prefix . $this->renderConditionString($condition, $bindings, "\n" . $condition->boolean . ' ');
        }//end foreach

        return implode($glue, $lines);
    }

    protected function renderPredicate(Predicate $predicate, array &$bindings = []) : string
    {

        $start = $this->renderPredicatePart($predicate->left, $bindings);

        $mode = $predicate->rightIsKey ? 'key' : 'value';
        $operator = $predicate->operator;

        if (!$operator) {
            return $start;
        }

        if ($this->isInOperator($operator)) {
            $mode = 'in';
        }

        if ($nullExpression = $this->getNullComparison($predicate)) {
            return "$start $nullExpression";
        }

        $end = $this->renderPredicatePart($predicate->right, $bindings, $mode);

        return "$start $predicate->operator $end";
    }

    protected function renderPredicatePart($operand, &$bindings = [], $mode = 'key') : string
    {
        if ($operand instanceof Str) {
            return $this->renderExpression($operand, $bindings);
        }

        if ($mode == 'key') {
            return $this->quote($operand, Dialect::NAME);
        }

        if ($mode == 'value') {
            $bindings[] = $operand;
            return '?';
        }

        if ($mode == 'in') {
            return $this->renderIn($operand, $bindings);
        }

        throw new InvalidArgumentException("Unknown mode '$mode'");
    }

    protected function renderIn($parameters, &$bindings = []) : string
    {
        $parameters = is_string($parameters) ? [$parameters] : $parameters;
        $questionMarks = [];

        foreach ($parameters as $parameter) {
            $questionMarks[] = '?';
            $bindings[] = $parameter;
        }

        return '(' . implode(',', $questionMarks) . ')';
    }

    /**
     * @param SQLExpression $expression
     * @param array $bindings
     *
     * @return string
     *
     * phpcs:disable Generic.NamingConventions.CamelCapsFunctionName
     */
    protected function renderSQLExpression(SQLExpression $expression, array &$bindings) : string
    {
        foreach ($expression->getBindings() as $binding) {
            $bindings[] = $binding;
        }
        return $expression->__toString();
        // phpcs:enable
    }


    protected function renderExpression(Str $expression, array &$bindings = []) : string
    {
        if ($expression instanceof SQLExpression) {
            return $this->renderSQLExpression($expression, $bindings);
        }

        if (!$this->dialect) {
            return $expression->__toString();
        }

        return $this->renderSQLExpression($this->dialect->expression($expression), $bindings);
    }

    /**
     * Quote with or without a dialect. Does not quote identifiers
     * (tables, columns, ...) so do this on your own if you don't
     * use a dialect.
     *
     * @param string|Str    $string
     * @param string        $type    (default: 'string')
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function quote($string, $type = 'string') : string
    {
        if ($string instanceof Str) {
            return $string->__toString();
        }
        if ($this->dialect) {
            return $this->dialect->quote($string, $type);
        }
        return $type == 'string' ? "'" . str_replace("'", "\'", $string) . "'" : $string;
    }

    protected function extend(array &$bindings, array $new) : void
    {
        if (!$new) {
            return;
        }
        array_push($bindings, ...$new);
    }

    /**
     * @param string $operator
     *
     * @return bool
     */
    protected function isInOperator(string $operator) : bool
    {
        $operator = strtolower($operator);
        return $operator == 'in' || $operator == 'not in';
    }

    /**
     * @param Predicate $predicate
     *
     * @return string
     */
    protected function getNullComparison(Predicate $predicate) : string
    {
        if ($predicate->right !== null) {
            return '';
        }

        $operator = strtolower($predicate->operator);

        if (in_array($operator, ['in', '='])) {
            return 'IS NULL';
        }

        if (in_array($operator, ['not in', '!=', '<>'])) {
            return 'IS NOT NULL';
        }

        return '';
    }
}