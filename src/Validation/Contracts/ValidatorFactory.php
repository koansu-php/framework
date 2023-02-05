<?php
/**
 *  * Created by mtils on 05.02.2023 at 11:48.
 **/

namespace Koansu\Validation\Contracts;

use Koansu\Validation\Exceptions\ValidatorFabricationException;

interface ValidatorFactory
{
    /**
     * Create a validator. Optionally pass the ormClass you want to validate by
     * this validator. This allows manipulating rules when extending the app.
     *
     * The ormClass is just a hint. self::get() will never be called to create
     * the validator. If you want to manipulate the rules call:
     * self::get(TheClass::class)->mergeRules($newRules)
     *
     * @param array $rules
     * @param string $ormClass (optional)
     *
     * @return Validator
     */
    public function create(array $rules, string $ormClass='') : Validator;

    /**
     * Get the (registered) validator for $ormClass.
     *
     * @param string $ormClass
     *
     * @return Validator
     * @throws ValidatorFabricationException
     */
    public function get(string $ormClass) : Validator;

    /**
     * Shortcut to create a validator and call validate.
     *
     * @param array $rules
     * @param array $input
     * @param object|null $ormObject (optional)
     * @param array $formats (optional)
     *
     * @return array
     */
    public function validate(array $rules, array $input, object $ormObject=null, array $formats=[]) : array;
}