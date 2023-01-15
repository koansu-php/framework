<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:07.
 **/

namespace Koansu\Expression;

use BadMethodCallException;
use InvalidArgumentException;

use Koansu\Core\Str;
use Koansu\Expression\Exceptions\LogicalConstraintException;

use function func_get_args;
use function get_class;
use function get_resource_type;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function is_resource;
use function is_scalar;

class Constraint extends Str
{
    /**
     * @var string
     **/
    protected $name = '';

    /**
     * @var string
     **/
    protected $operator = '';


    /**
     * @var array
     **/
    protected $parameters = [];

    /**
     * @var string
     **/
    protected $toStringFormat = 'operator';

    /**
     * @var array
     **/
    protected $allowedOperators = [];

    /**
     * @param string $name
     * @param array $parameters
     * @param string $operator (optional)
     * @param string $toStringFormat
     */
    public function __construct(string $name, array $parameters=[], string $operator='', string $toStringFormat='operator')
    {
        parent::__construct();
        $this->setName($name);
        $this->setParameters($parameters);
        $this->setOperator($operator);
        $this->setToStringFormat($toStringFormat);
    }

    /**
     * Return the constraint name.
     *
     * @return string
     **/
    public function name() : string
    {
        return $this->name;
    }

    /**
     * Return constraints parameters.
     *
     * @return array
     **/
    public function parameters() : array
    {
        return $this->parameters;
    }

    public function operator() : string
    {
        return $this->operator;
    }

    /**
     * Set the name of this constraint.
     *
     * @param string $name
     *
     * @return self
     **/
    public function setName(string $name) : Constraint
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the parameters of this constraint.
     *
     * @param array $parameters
     *
     * @return self
     **/
    public function setParameters(array $parameters) : Constraint
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set the operator of this constraint.
     *
     * @param string $operator
     *
     * @return self
     **/
    public function setOperator(string $operator) : Constraint
    {
        if ($this->allowedOperators && !in_array($operator, $this->allowedOperators)) {
            throw new LogicalConstraintException('This constraint only accepts operators: ' . implode(',', $this->allowedOperators));
        }

        $this->operator = $operator;
        return $this;
    }

    /**
     * Return the toStringFormat, which is either operator or
     * name.
     * operator: = value
     * name:     equals:value
     *
     * @return string
     **/
    public function getToStringFormat() : string
    {
        return $this->toStringFormat;
    }

    /**
     * Set the toStringFormat, which is either operator or
     * name.
     *
     * @param string $format
     *
     * @return self
     **/
    public function setToStringFormat(string $format) : Constraint
    {

        if (!in_array($format, ['operator', 'name'])) {
            throw new InvalidArgumentException("Format can be operator|name");
        }

        $this->toStringFormat = $format;

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
    public function allowOperators($operators) : Constraint
    {
        $operators = is_array($operators) ? $operators : func_get_args();
        if ($this->allowedOperators && $this->allowedOperators != $operators) {
            throw new BadMethodCallException('You can only set the allowed operators once.');
        }
        $this->allowedOperators = $operators;
        return $this->setOperator($this->operator);
    }

    /**
     * @return string
     **/
    public function __toString() : string
    {
        if ($this->toStringFormat == 'operator') {
            return $this->renderOperatorString();
        }
        return $this->renderNameString();
    }

    /**
     * Render the operator formatted string
     *
     * @return string
     **/
    protected function renderOperatorString() : string
    {

        $parameters = $this->parameters ? $this->renderParameters($this->parameters) : '';

        if ($this->operator) {
            return $this->operator . ($parameters ? " $parameters" : '');
        }

        return $this->name . "($parameters)";
    }

    /**
     * Render the name formatted string
     *
     * @return string
     **/
    protected function renderNameString() : string
    {
        $parameters = $this->parameters ? $this->renderParameters($this->parameters) : '';

        return $parameters ? "$this->name:$parameters" : $this->name;
    }

    /**
     * Renders the parameters as a string
     *
     * @param array $parameters
     * @param bool  $recursion (default=false)
     *
     * @return string
     **/
    protected function renderParameters(array $parameters, bool $recursion=false) : string
    {

        $isOperatorFormat = $this->toStringFormat == 'operator';

        $separator = $isOperatorFormat ? ', ' : ',';

        $rendered = [];

        foreach ($parameters as $parameter) {

            if ($parameter === null) {
                $rendered[] = 'null';
                continue;
            }

            if (is_resource($parameter)) {
                $rendered[] = $this->toStringFormat == 'operator' ? 'resource of type ' . get_resource_type($parameter) : get_resource_type($parameter);
                continue;
            }

            if (is_object($parameter)) {
                $rendered[] = get_class($parameter);
                continue;
            }

            if (!is_array($parameter)) {
                $rendered[] = "$parameter";
                continue;
            }

            if (count($parameter) > 80  || $recursion  || !$this->containsOnlyScalars($parameter)) {
                $rendered[] = '[' . $this->renderParameters($parameter) . ']';
                continue;
            }

            if ($isOperatorFormat) {
                $rendered[] = '(' . $this->renderParameters($parameter, true) . ')';
                continue;
            }

            $rendered[] = $this->renderParameters($parameter, true);

        }

        return implode($separator, $rendered);
    }

    /**
     * @param array $values
     *
     * @return bool
     **/
    protected function containsOnlyScalars(array $values) : bool
    {
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                return false;
            }
        }
        return true;
    }
}