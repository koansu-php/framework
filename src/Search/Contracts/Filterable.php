<?php
/**
 *  * Created by mtils on 29.10.2022 at 13:19.
 **/

namespace Koansu\Search\Contracts;

/**
 * Filterable is the simplistic Queryable. It does not support operators.
 *
 * $pool->filter(['foo' => 'bar])->filter('active', true)
 */
interface Filterable
{
    /**
     * Add a filter manually. Pass an array to add multiple values.
     *
     * @param string|array $key
     * @param mixed        $value (optional)
     *
     * @return Filterable|iterable
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function filter($key, $value=null);
}