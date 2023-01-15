<?php
/**
 *  * Created by mtils on 24.12.2022 at 09:12.
 **/

namespace Koansu\Expression;

use BadMethodCallException;
use InvalidArgumentException;
use Koansu\Core\Str;
use OutOfBoundsException;

use function array_filter;
use function array_values;
use function func_get_args;
use function implode;
use function in_array;
use function is_array;
use function strtoupper;

abstract class LogicalGroup extends Str
{
    /**
     * @var string
     **/
    protected $operator = 'and';

    /**
     * @var string
     **/
    protected $toStringSeparator = ' AND ';

    /**
     * @var array
     **/
    protected $expressions = [];

    /**
     * @var array
     **/
    protected $supportedOperators = ['and', 'or', 'nand', 'nor'];

    /**
     * @var bool
     **/
    protected $allowMultipleConnectives = true;

    /**
     * @var array
     **/
    protected $allowedConnectives = [];

    /**
     * @var bool
     **/
    protected $allowNesting = true;

    /**
     * @var array
     **/
    protected $allowedOperators = [];

    /**
     * @var int
     **/
    protected $maxConditions = 0;

    /**
     * Return the operator (AND|OR|NOT|NOR|NAND).
     *
     * @return string (AND|OR|NOT|NOR|NAND)
     **/
    public function operator() : string
    {
        return $this->operator;
    }

    /**
     * Return all expressions
     *
     * @return Str[]
     **/
    public function expressions() : array
    {
        return $this->expressions;
    }

    /**
     * Add an expression.
     *
     * @param Str $expression
     *
     * @return self
     **/
    public function add(Str $expression) : LogicalGroup
    {
        $this->typeCheck($expression);
        $this->applyRestrictions($expression);
        $this->expressions[] = $expression;
        return $this;
    }

    /**
     * Remove an expression.
     *
     * @param Str $expression
     *
     * @return self
     **/
    public function remove(Str $expression) : LogicalGroup
    {
        $filtered = array_filter($this->expressions, function ($known) use ($expression) {
            return "$known" != "$expression";
        });

        $this->expressions = array_values($filtered);

        return $this;
    }

    /**
     * Remove all expressions.
     *
     * @return self
     **/
    public function clear() : LogicalGroup
    {
        $this->expressions = [];
        return $this;
    }

    /**
     * @param string $operator
     *
     * @return self
     **/
    public function setOperator(string $operator) : LogicalGroup
    {
        if (!in_array($operator, $this->supportedOperators)) {
            $list = implode('|', $this->supportedOperators);
            throw new InvalidArgumentException("operator has to be $list, not $operator");
        }

        if ($this->allowedConnectives && !in_array($operator, $this->allowedConnectives)) {
            throw new InvalidArgumentException("This logical group only accept connectives:" . implode($this->allowedConnectives));
        }
        $this->operator = $operator;
        $this->toStringSeparator = ' ' . strtoupper($operator) . ' ';
        return $this;
    }

    /**
     * Return the allowed connectives (operators)
     *
     * @return string[]
     **/
    public function allowedConnectives() : array
    {
        return $this->allowedConnectives;
    }

    /**
     * Restrict the supported connectives (logical operators) to the passed
     * connectives. This can only be done once.
     *
     * @param string|array $connectives
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function allowConnectives($connectives) : LogicalGroup
    {
        $connectives = is_array($connectives) ? $connectives : func_get_args();
        if ($this->allowedConnectives && $connectives != $this->allowedConnectives) {
            throw new BadMethodCallException('You can only set the allowed connectives once.');
        }
        $this->allowedConnectives = $connectives;
        $this->checkRestrictions();
        return $this;
    }

    /**
     * Is a change from or to and or any other connective allowed
     *
     * @return bool
     **/
    public function areMultipleConnectivesAllowed() : bool
    {
        if (count($this->allowedConnectives) == 1) {
            return false;
        }
        return $this->allowMultipleConnectives;
    }

    /**
     * Let this group (and all subgroups) only have one connective.
     * So if the operator of this one is "or", there will be no chance to
     * add groups with another operator.
     *
     * @return self
     **/
    public function forbidMultipleConnectives() : LogicalGroup
    {
        $this->allowMultipleConnectives = false;
        $this->checkRestrictions();
        return $this;
    }

    /**
     * Is it allow to add subgroups?
     *
     * @return bool
     **/
    public function isNestingAllowed() : bool
    {
        return $this->allowNesting;
    }

    /**
     * Don't allow sub logical groups.
     *
     * @return self
     **/
    public function forbidNesting() : LogicalGroup
    {
        $this->allowNesting = false;
        $this->checkRestrictions();
        return $this;
    }

    /**
     * Return the allowed CONSTRAINT operators.
     *
     * @return string[]
     **/
    public function allowedOperators() : array
    {
        return $this->allowedOperators;
    }

    /**
     * Force the CONSTRAINTS to only support the passed operator(s).
     *
     * @param array|string $operators
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function allowOperators($operators) : LogicalGroup
    {
        if ($this->allowedOperators) {
            throw new BadMethodCallException('You can only set the allowed operators once.');
        }
        $this->allowedOperators = is_array($operators) ? $operators : func_get_args();
        $this->checkRestrictions();
        return $this;
    }

    /**
     * Return the maximum amount of conditions added to this group.
     *
     * @return int
     **/
    public function maxConditions() : int
    {
        return $this->maxConditions;
    }

    /**
     * Restrict the maximum number of conditions added to this group.
     * CAUTION Because of the complex effects of this restriction nesting is
     * automatically forbidden if setting some max conditions.
     *
     * @param int $max
     *
     * @return self
     **/
    public function allowMaxConditions(int $max) : LogicalGroup
    {
        if ($this->maxConditions) {
            throw new BadMethodCallException('You can only set the maximum conditions only once.');
        }
        $this->maxConditions = $max;
        $this->allowNesting = false;
        $this->checkRestrictions();
        return $this;
    }

    /**
     * This is just a method to do some custom type checking before
     * adding it to the LogicalGroup.
     *
     * @param Str $expression
     **/
    protected function typeCheck(Str $expression) : void
    {
        //
    }

    /**
     * Check the applied restrictions on $expression. allowedConnectives,
     * allowedOperators, ....
     *
     * @param Str   $expression
     * @param bool  $beforeAdding (default:true)
     **/
    protected function applyRestrictions(Str $expression, bool $beforeAdding=true) : void
    {

        if ($beforeAdding && $this->maxConditions && count($this->expressions) >= $this->maxConditions) {
            throw new OutOfBoundsException("This group can only hold a maximum of {$this->maxConditions} conditions.");
        }

        if ($this->allowedOperators && ($expression instanceof Constraint || $expression instanceof Condition)) {
            $expression->allowOperators($this->allowedOperators);
        }

        if ($expression instanceof LogicalGroup) {
            $this->applyGroupRestrictions($expression);
        }

        if (!$beforeAdding && $this->maxConditions && count($this->expressions) > $this->maxConditions) {
            throw new OutOfBoundsException("This group can only hold a maximum of {$this->maxConditions} conditions.");
        }
    }

    protected function applyGroupRestrictions(LogicalGroup $group) : void
    {
        if (!$this->allowNesting) {
            throw new UnsupportedParameterException("This logical group does not allow nested groups");
        }

        if ($this->allowedConnectives && !in_array($group->operator(), $this->allowedConnectives)) {
            throw new UnsupportedParameterException("This logical group only accept connectives:" . implode($this->allowedConnectives));
        }

        if ($this->allowedOperators) {
            $group->allowOperators($this->allowedOperators);
        }

        if ($this->allowMultipleConnectives) {
            return;
        }

        if ($group->operator() != $this->operator()) {
            $comparison = $group->operator() . ' != '. $this->operator();
            throw new UnsupportedParameterException("This logical group forbids multiple connectives ($comparison)");
        }

        $group->allowConnectives($this->operator());
    }

    protected function checkRestrictions()
    {
        foreach ($this->expressions as $expression) {
            $this->applyRestrictions($expression, false);
        }
    }

    /**
     * Find expressions by its name, string representation, operator, class or operand.
     *
     * @param array $attributes
     * @param array $expressions (optional)
     *
     * @return array
     **/
    protected function findExpressions(array $attributes, $expressions=null)
    {

        $search = [
            'string'   => isset($attributes['string'])   ? $attributes['string']   : '*',
            'name'     => isset($attributes['name'])     ? $attributes['name']     : '*',
            'operator' => isset($attributes['operator']) ? $attributes['operator'] : '*',
            'operand'  => isset($attributes['operand'])  ? $attributes['operand']  : '*',
            'class'    => isset($attributes['class'])    ? $attributes['class']    : '*',
        ];

        $expressions = $expressions ?: $this->allExpressions();

        $matches = [];

        foreach ($expressions as $expression) {

            if ($this->matchesAttributes($expression, $search)) {
                $matches[] = $expression;
            }
        }

        return $matches;
    }

    /**
     * Recursively collect all expressions.
     *
     * @param array $expressions (optional)
     * @param array $all (optional)
     *
     * @return array
     **/
    protected function allExpressions(array $expressions=null, &$all=null)
    {

        $expressions = $expressions ?: $this->expressions();
        $all = $all ?: [];

        foreach ($expressions as $expression) {

            $all[] = $expression;

            if ($expression instanceof HasExpressions) {
                $this->allExpressions($expression->expressions(), $all);
            }

        }

        return $all;
    }

    /**
     * Check if $expression matches $search. Query only the objects which have
     * the passed criteria methods/keys by passing a class.
     *
     * @param ExpressionContract $expression
     * @param array              $search
     *
     * @return bool
     **/
    protected function matchesAttributes(ExpressionContract $expression, array $search)
    {

        if ($search['class'] !== '*' && !$expression instanceof $search['class']) {
            return false;
        }

        if ($search['name'] !== '*' && $expression->name() != $search['name']) {
            return false;
        }

        if ($search['operator'] !== '*' && $expression->operator() != $search['operator']) {
            return false;
        }

        if ($search['operand'] !== '*' && (string)$expression->operand() != $search['operand']) {
            return false;
        }

        if ($search['string'] !== '*' && "$expression" != $search['string']) {
            return false;
        }

        return true;

    }

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    protected function renderLogicalGroupString()
    {
        $parts = [];

        foreach ($this->expressions() as $expression) {

            if ($expression instanceof LogicalGroupContract) {
                $parts[] = '(' . "$expression" . ')';
                continue;
            }

            $parts[] = $expression;

        }

        return implode($this->toStringSeparator, $parts);
    }
}