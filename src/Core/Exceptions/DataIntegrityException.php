<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:25.
 **/

namespace Koansu\Core\Exceptions;

use RuntimeException;

/**
 * Throw a DataIntegrityException if an integrity check failed or otherwise
 * corrupted data was found.
 **/
class DataIntegrityException extends RuntimeException
{

}