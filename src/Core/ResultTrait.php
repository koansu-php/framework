<?php /** @noinspection PhpDocMissingThrowsInspection */

/**
 *  * Created by mtils on 26.12.2022 at 06:19.
 **/

namespace Koansu\Core;

use IteratorAggregate;
use Koansu\Core\Contracts\Result;

/**
 * @see Result
 * @mixin IteratorAggregate
 */
trait ResultTrait
{
    /**
     * Get the first result (if exists)
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function first()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        foreach ($this->getIterator() as $result) {
            return $result;
        }
        return null;
    }

    /**
     * Get the last result (if exists)
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     **/
    public function last()
    {
        $result = null;

        /** @noinspection PhpUnhandledExceptionInspection */
        foreach ($this->getIterator() as $result) {
            // Empty working code ...
        }

        return $result;
    }
}