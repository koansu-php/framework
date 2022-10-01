<?php
/**
 *  * Created by mtils on 28.08.2022 at 08:52.
 **/

namespace Koansu\DependencyInjection\Exceptions;

/**
 * This exception is thrown if the container cannot find the concrete class,
 * also in create(). The naming is because it is not meant as a global
 * ClassNotFoundException
 */
class ConcreteClassNotFoundException extends ContainerException
{
    //
}