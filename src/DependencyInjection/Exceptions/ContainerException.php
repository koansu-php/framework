<?php
/**
 *  * Created by mtils on 14.11.20 at 11:28.
 **/

namespace Koansu\DependencyInjection\Exceptions;


use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
    //
}