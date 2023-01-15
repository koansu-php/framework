<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:16.
 **/

namespace Koansu\Expression;

use BadMethodCallException;
use InvalidArgumentException;

use Koansu\Core\Str;

use function func_get_args;
use function implode;
use function is_array;
use function is_scalar;


class Condition extends Str
{
    /**
     * @var string|Str
     **/
    protected $operand;


    /**
     * @var Constraint|ConstraintGroup
     **/
    protected $constraint;

    /**
     * @var array
     **/
    protected $allowedOperators = [];

    /**
     * @param string|Str                    $operand (optional)
     * @param Constraint|ConstraintGroup    $constraint (optional)
     **/
    public function __construct($operand=null, $constraint=null)
    {
        parent::__construct();
        if ($operand) {
            $this->setOperand($operand);
        }
        if ($constraint) {
            $this->setConstraint($constraint);
        }
    }

    /**
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function operand()
    {
        return $this->operand;
    }

    /**
     * @return Constraint|ConstraintGroup
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function constraint()
    {
        return $this->constraint;
    }

    /**
     * @param string|Str $operand
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setOperand($operand) : Condition
    {
        $this->operand = $this->checkOperand($operand);
        return $this;
    }

    /**
     * Return all expressions
     *
     * @return array
     **/
    public function expressions() : array
    {

        $expressions = [];

        if ($this->operand instanceof Str) {
            $expressions[] = $this->operand;
        }

        if ($this->constraint instanceof Str) {
            $expressions[] = $this->constraint;
        }

        return $expressions;

    }

    /**
     * @param Constraint|ConstraintGroup $constraint
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setConstraint($constraint) : Condition
    {
        $this->constraint = $this->checkConstraint($constraint);
        return $this;
    }

    /**
     * Return the allowed operators of this constraint.
     *
     * @return string[]
     **/
    public function allowedOperators() : array
    {
        return $this->allowedOperators;
    }

    /**
     * Force the constraint to only support the passed operator(s).
     *
     * @param array|string $operators
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function allowOperators($operators) : Condition
    {
        $operators = is_array($operators) ? $operators : func_get_args();

        if ($this->allowedOperators && $this->allowedOperators != $operators) {
            throw new BadMethodCallException('You can only set the allowed operators once.');
        }

        $this->allowedOperators = $operators;

        if ($this->constraint) {
            $this->checkConstraint($this->constraint);
        }

        return $this;
    }

    /**
     * Checks the type of the operand
     *
     * @param mixed $operand
     *
     * @return mixed
     *
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function checkOperand($operand)
    {
        if (!is_scalar($operand) && !$operand instanceof Str) {
            throw new InvalidArgumentException('Condition only acceps scalars and Str, not ' . Type::of($operand));
        }
        return $operand;
    }

    /**
     * Checks the type of the constraint
     *
     * @param mixed $constraint
     *
     * @return Constraint|ConstraintGroup
     *
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function checkConstraint($constraint)
    {
        if (!$constraint instanceof Constraint && !$constraint instanceof ConstraintGroup) {
            throw new InvalidArgumentException("Constraint has to be Constraint or ConstraintGroup, not " . Type::of($constraint));
        }
        if ($this->allowedOperators && $constraint instanceof Constraint) {
            $constraint->allowOperators($this->allowedOperators);
        }
        return $constraint;
    }

    /**
     * @return string
     **/
    public function __toString() : string
    {
        $parts = [];

        if ($this->operand) {
            $parts[] = (string)$this->operand;
        }

        if ($this->constraint) {
            $parts[] = (string)$this->constraint;
        }

        return implode(' ', $parts);
    }
}