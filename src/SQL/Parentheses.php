<?php
/**
 *  * Created by mtils on 24.12.2022 at 06:51.
 **/

namespace Koansu\SQL;

use ArrayIterator;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;
use IteratorAggregate;
use Koansu\Core\Str;
use Koansu\Core\Type;
use Koansu\Core\Contracts\Queryable;

use function call_user_func;
use function func_get_args;
use function func_num_args;
use function is_string;

/**
 * Class ParentheticalExpression
 *
 * @package Ems\Contracts\Model\Database
 *
 * @property-read string                                boolean (AND|OR)
 * @property-read Predicate[]|Parentheses[]|Str[]       expressions
 */
class Parentheses implements IteratorAggregate, Queryable, Countable
{
    /**
     * The connector between the expressions (typically AND|OR)
     *
     * @var string
     */
    protected $boolean = '';

    /**
     * @var Predicate[]|Parentheses[]|Str[]
     */
    protected $expressions = [];

    /**
     * Parentheses constructor.
     *
     * @param string $boolean (optional)
     * @param array  $expressions (optional)
     */
    public function __construct(string $boolean = '', array $expressions = [])
    {
        $this->boolean = $boolean;
        $this->expressions = $expressions;
    }

    /**
     * {@inheritDoc}
     *
     * @param string|Str|Closure|Predicate $operand
     * @param mixed $operatorOrValue (optional)
     * @param mixed $value (optional)
     *
     * @return self
     *
     * @throws InvalidArgumentException
     **/
    public function where($operand, $operatorOrValue = null, $value = null) : Queryable
    {
        if (func_num_args() !== 1) {
            $this->expressions[] = new Predicate(...func_get_args());
            return $this;
        }

        if ($operand instanceof Str || $operand instanceof Predicate || is_string($operand)) {
            $this->expressions[] = $operand;
            return $this;
        }

        if ($operand instanceof Closure) {
            $operand($this);
            return $this;
        }

        throw new InvalidArgumentException('I do not support ' . Type::of($operand));
    }

    /**
     * Append a new braced group of expressions. Either use a callable
     * to add your expressions or use the return value.
     *
     * @param string    $boolean (and|or)
     * @param ?callable $builder (optional)
     *
     * @return $this
     *
     * @example $query('or', function (Parentheses $group) {
     *    $group->where('foo', '<>', 'bar');
     * });
     *
     * @example $query('or')->where('foo', '<>', 'bar');
     */
    public function __invoke(string $boolean, callable $builder = null) : Parentheses
    {
        $group = new static($boolean);
        if ($builder) {
            call_user_func($builder, $group);
        }
        $this->expressions[] = $group;
        return $group;
    }

    /**
     * Retrieve an external iterator
     *
     * @link   https://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return ArrayIterator An instance of an object implementing <b>Iterator</b> or <b>Traversable</b>
     * @throws Exception on failure.
     * @since  5.0
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->expressions);
    }

    /**
     * Count elements of an object
     *
     * @link   https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * @since  5.1
     */
    public function count() : int
    {
        return count($this->expressions);
    }

    /**
     * Clear all expressions.
     */
    public function clear()
    {
        $this->expressions = [];
    }

    /**
     * @return Parentheses|Predicate|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function first()
    {
        return $this->expressions[0] ?? null;
    }

    /**
     * @param string $name
     *
     * @return Predicate[]|string|null
     */
    public function __get(string $name)
    {
        if ($name === 'boolean' || $name === 'bool') {
            return $this->boolean;
        }

        if ($name === 'expressions') {
            return $this->expressions;
        }

        return null;
    }
}