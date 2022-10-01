<?php
/**
 *  * Created by mtils on 14.11.20 at 11:30.
 **/

namespace Koansu\DependencyInjection\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class BindingNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    //
}