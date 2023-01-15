<?php
/**
 *  * Created by mtils on 24.12.2022 at 06:56.
 **/

namespace Koansu\SQL;

use Koansu\Core\Str;

use function func_num_args;

/**
 * Class Predicate
 *
 * The predicate stores a sql condition. It is not an Expression\Condition
 * to have fewer dependencies and keep SQL stuff as fast and simple
 * as it can.
 * It is assumed (by this class and others in this namespace) that $left is
 * a column (and should be escaped like this).
 * If you want to pass something unescaped you have to wrap it into a Str.
 *
 * This right is the opposite. It is assumed that right is a value and will be
 * assumed as a prepared parameter. To directly write into the string use an
 * Expression.
 *
 * @package Koansu\SQL
 *
 * @property-read string|Str    left       Automatically evaluates to a column/alias
 * @property-read string        operator
 * @property-read mixed         right      Automatically evaluates to prepared parameters
 * @property-read boolean       rightIsKey Is the right operand by default a key? Normally not, only in JoinClause
 */
class Predicate
{

    /**
     * @var string|Str
     */
    protected $left = '';

    /**
     * @var string
     */
    protected $operator = '=';

    /**
     * @var mixed
     */
    protected $right;

    /**
     * @var boolean
     */
    protected $rightIsKey = false;

    public function __construct($left = '', $operatorOrRight = '', $right = null)
    {
        $numArgs = func_num_args();

        if ($numArgs === 0) {
            return;
        }

        $this->left = $left;

        if ($numArgs === 1) {
            $this->operator = '';
            return;
        }

        if ($numArgs === 2) {
            $this->right = $operatorOrRight;
            return;
        }

        $this->operator = $operatorOrRight;
        $this->right = $right;
    }

    /**
     * @param string $name
     *
     * @return mixed|string
     */
    public function __get(string $name)
    {
        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        switch ($name) {
            case 'left':
                return $this->left;
            case 'operator':
                return $this->operator;
            case 'right':
                return $this->right;
            case 'rightIsKey':
                return $this->rightIsKey;
        }
        return null;
    }

    /**
     * @param bool $isKey (default:true)
     *
     * @return $this
     */
    public function rightIsKey(bool $isKey = true) : Predicate
    {
        $this->rightIsKey = $isKey;
        return $this;
    }
}