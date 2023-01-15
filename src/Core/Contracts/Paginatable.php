<?php
/**
 *  * Created by mtils on 29.10.2022 at 13:08.
 **/

namespace Koansu\Core\Contracts;

/**
 * A PaginatableResult allows to paginate the result (later).
 *
 * @example foreach (User::where('name', 'John')->paginate(2) as $user)
 **/
interface Paginatable
{
    /**
     * Paginate the result. Return whatever paginator you use.
     * The paginator must be iterable and return the sliced result.
     *
     * @param int $page    (optional)
     * @param int $perPage (optional)
     *
     * @return iterable A paginator instance or just an array
     **/
    public function paginate(int $page = 1, int $perPage = 0) : iterable;
}