<?php
/**
 *  * Created by mtils on 24.12.2022 at 06:47.
 **/

namespace Koansu\Core\Contracts;

use Closure;
use Koansu\Core\Str;

/**
 * A Queryable is an interface for more complex searchable objects.
 * It is a Filterable with operator support.
 **/
interface Queryable
{

    /**
     * The where method creates a and connected group, in which all conditions
     * have to apply to make the whole conditional apply.
     * $operand is auto-converted to an expression if none passed.
     * It is a AND gate (0+0=0|0+1=0|1+0=0|1+1=1)
     *
     * This method has the following signatures:
     *
     * @example where('name', 'Ralf') // name = Ralf
     * @example where('name', 'Ralf')->where('birthday', '2017-01-01') // name = 'Ralf' AND birthday = '2017-01-01'
     * @example where('category_id', 'in', [1,3,4]) // category_id IN (1,3,4)
     * @example where(Str('CONCAT(firstName,lastName)'), 'like', 'Ralf') // CONCAT(firstName, lastName) LIKE 'Ralf'
     * @example where(new Condition('name', 'equals:Ralf')) // name = 'Ralf'
     * @example where('age', 'min:5|max:18') // age >= 5 AND age <= 18
     * @example where('name', 'Ralf')->where(function (Conditional $conditional) {
     *     $conditional->orWhere('age', '>=', 5)
     *     $conditional->orWhere('age', '<=', 18)
     * }) // name = 'Ralf' AND (age >= 5 OR age <= 18)
     * @example where('string') // Does pass thru your statement (like whereRaw() in Eloquent)
     *
     * @param string|Str|Closure    $operand
     * @param mixed                 $operatorOrValue (optional)
     * @param mixed                 $value           (optional)
     *
     * @return self
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function where($operand, $operatorOrValue = null, $value = null) : Queryable;

}