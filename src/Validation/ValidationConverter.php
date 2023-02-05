<?php
/**
 *  * Created by mtils on 05.02.2023 at 10:25.
 **/

namespace Koansu\Validation;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\ExtendableTrait;
use Koansu\Validation\Contracts\Validation;

/**
 * This object is just a host to convert validation objects into a different
 * class or render it into string.
 * You could call:
 * @example ValidationConverter::convert($validation, MessageBag::class)
 * ...to convert it into a message bag object (symfony,laravel,whatever)
 * but the class is so generic you could also render a template by it
 *
 */
class ValidationConverter implements Extendable
{
    use ExtendableTrait;

    /**
     * Convert a validation into something different (message bag...)
     *
     * @param Validation $validation
     * @param string     $format
     * @param array      $keyTitles (optional)
     * @param array      $customMessages (optional)
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function convert(Validation $validation, string $format, array $keyTitles = [], array $customMessages = [])
    {
        return $this->callExtension($format, $this->getExtension($format), [$validation, $format, $keyTitles, $customMessages]);
    }
}