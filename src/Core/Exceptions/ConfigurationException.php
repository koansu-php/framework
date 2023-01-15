<?php
/**
 *  * Created by mtils on 26.10.2022 at 08:03.
 **/

namespace Koansu\Core\Exceptions;

use RuntimeException;

/**
 * This is a common base class for all configuration errors. All unhandled
 * situations caused by wrong or missing configuration should throw an ConfigurationException
 */
class ConfigurationException extends RuntimeException
{
    //
}