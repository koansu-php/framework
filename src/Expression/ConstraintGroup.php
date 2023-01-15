<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:20.
 **/

namespace Koansu\Expression;

use ArrayAccess;
use InvalidArgumentException;
use Koansu\Core\ConstraintParsingTrait;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\Core\Str;
use Koansu\Core\Type;

use function array_values;
use function implode;
use function is_array;

class ConstraintGroup extends LogicalGroup implements ArrayAccess
{
    use ConstraintParsingTrait;

    /**
     * @var array
     **/
    protected $constraints = [];

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
    protected $supportedOperators = ['and', 'or', 'nand', 'nor'];

    /**
     * @param array|string $definition (optional)
     **/
    public function __construct($definition=null)
    {
        parent::__construct();
        if ($definition) {
            $this->fill($definition);
        }
    }

    /**
     * Return all expressions
     *
     * @return Str[]
     **/
    public function expressions() : array
    {
        return array_values($this->constraints);
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
        parent::add($expression);
        /** @var Constraint $expression */
        $this->constraints[$expression->name()] = $expression;
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
        $this->typeCheck($expression);
        /** @var Constraint $expression */
        unset($this->constraints[$expression->name()]);
        return $this;
    }

    /**
     * Return all constraints
     *
     * @return Constraint[]
     **/
    public function constraints() : array
    {
        return $this->constraints;
    }

    /**
     * Fill the constraint by a string.
     *
     * @param array|string $definition
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function fill($definition) : ConstraintGroup
    {
        $this->clear();
        $this->merge($definition);
        return $this;
    }

    /**
     * Merge the constraints with others
     *
     * @param array|string $definition
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function merge($definition) : ConstraintGroup
    {
        $constraints = $this->explodeConstraints($definition);

        foreach ($constraints as $index=>$definition) {

            list($name, $parameters) = is_array($definition) ?
                [$index, $definition] :
                $this->nameAndParameters($definition);

            $constraint = $this->newConstraint(
                Type::snake_case($name),
                $parameters,
                ''
            );
            $this->constraints[$constraint->name()] = $constraint;

        }
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return self
     **/
    public function clear() : LogicalGroup
    {
        parent::clear();
        $this->constraints = [];
        return $this;
    }

    /**
     * @param string $name
     *
     * @return ?array
     **/
    public function __get(string $name) : ?array
    {

        $name = Type::snake_case($name);

        if (!$this->__isset($name)) {
            throw new KeyNotFoundException("No constraint with key $name");
        }

        $parameters = $this->constraints[$name]->parameters();

        $count = count($parameters);

        // For easier access return just null if no parameters were set
        if ($count == 0) {
            return null;
        }

        // For easier access return just the first parameter
        if ($count == 1) {
            return $parameters[0];
        }

        return $parameters;
    }

    /**
     * @param string $name
     * @param mixed  $parameters
     *
     * @return void
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __set(string $name, $parameters)
    {
        $constraint = $this->newConstraint(
            Type::snake_case($name),
            (array)$parameters,
            ''
        );

        $this->constraints[$constraint->name()] = $constraint;
    }

    /**
     * @param string $name
     *
     * @return bool
     **/
    public function __isset(string $name) : bool
    {
        $name = Type::snake_case($name);
        return isset($this->constraints[$name]);
    }

    /**
     * @param string $name
     *
     * @return void
     **/
    public function __unset(string $name) : void
    {
        $name = Type::snake_case($name);
        unset($this->constraints[$name]);
    }

    /**
     * Check if $offset exists.
     *
     * @param mixed $offset
     *
     * @return bool
     **/
    public function offsetExists($offset) : bool
    {
        return $this->__isset($offset);
    }

    /**
     * Get value of $offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     **/
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->constraints[$offset];
    }

    /**
     * Set the value of $offset.
     *
     * @param mixed $offset
     * @param mixed $value
     **/
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->checkType($value);
        if ($offset != $value->name()) {
            throw new InvalidArgumentException("The offset has to be the name of the constraint");
        }
        $this->constraints[$value->name()] = $value;
    }

    /**
     * Unset $offset.
     *
     * @param mixed $offset
     **/
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * Returns a rendered version of the constraints.
     *
     * @return string
     **/
    public function __toString() : string
    {
        $constraints = [];
        foreach ($this->constraints as $name=>$constraint) {
            $constraints[] = "$constraint";
        }
        return implode($this->toStringSeparator, $constraints);
    }

    /**
     * Throws an error if the expression is not a constraint.
     *
     * @param Str $expression
     */
    protected function typeCheck(Str $expression) : void
    {
        if (!$expression instanceof Constraint) {
            throw new InvalidArgumentException("ConstraintGroup works only with Constraint not " . Type::of($expression));
        }
    }

    /**
     * Create a new Constraint.
     *
     * @param $name
     * @param $parameters
     * @param $operator
     * @return Constraint
     */
    protected function newConstraint($name, $parameters, $operator) : Constraint
    {
        return new Constraint($name, $parameters, $operator, 'operator');
    }
}