<?php
/**
 *  * Created by mtils on 29.10.2022 at 12:50.
 **/

namespace Koansu\Core\Contracts;

use IteratorAggregate;

/**
 * A Result should always be used if you return something
 * from a model.
 * You should not retrieve the results inside your repository, search
 * whatever class which returns results. At this point it's better to
 * build a proxy object that will retrieve the results on demand.
 * This is because you often don't know if the user of your model
 * will paginate, return the whole result or discard the request later
 * if something other goes wrong. So delay your expensive operations.
 * This interface is not countable by intent.
 * Iterators allow to process rows one by one which will not explode
 * your memory on large result sets.
 * The result must create a new iterator on every call of getIterator()
 * (foreach)
 * A DB Query object is a perfect candidate for a Result
 * So you could write foreach (User::where('name', 'John') as $user) instead
 * of having to have a separate method for retrieving.
 **/
interface Result extends IteratorAggregate
{
    /**
     * Return the first result.
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function first();

    /**
     * Return the last result.
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function last();
}