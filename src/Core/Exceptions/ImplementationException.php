<?php
/**
 *  * Created by mtils on 11.09.2022 at 07:55.
 **/

namespace Koansu\Core\Exceptions;

use LogicException;

/**
 * This is the base class for wrong implemented code. Throwing this exception
 * means a class, method, function,... has to be fixed in code to solve the
 * problem.
 * It is when an abstract class or trait detects that the extending class was
 * wrongly implemented.
 */
class ImplementationException extends LogicException
{
    //
}