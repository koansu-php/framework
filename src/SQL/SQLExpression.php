<?php
/**
 *  * Created by mtils on 22.12.2022 at 21:40.
 **/

namespace Koansu\SQL;

use Koansu\Core\Str;

use function array_merge;
use function is_array;


class SQLExpression extends Str
{
    /**
     * @var array
     */
    protected $bindings = [];

    public function __construct($rawString='', array $bindings=[])
    {
        parent::__construct($rawString, 'application/sql');
        $this->bindings = $bindings;
    }

    /**
     * Return the bindings of this expression.
     *
     * @return array
     */
    public function getBindings() : array
    {
        return $this->bindings;
    }

    public function bind($key, $value='') : SQLExpression
    {
        if (!is_array($key)) {
            return $this->bind([$key => $value]);
        }
        $this->bindings = array_merge($this->bindings, $key);
        return $this;
    }

    /**
     * Replace all bindings with the passed ones.
     *
     * @param array $bindings
     *
     * @return $this
     */
    public function setBindings(array $bindings) : SQLExpression
    {
        $this->bindings = $bindings;
        return $this;
    }

}