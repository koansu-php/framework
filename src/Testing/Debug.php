<?php
/**
 *  * Created by mtils on 19.12.2022 at 05:44.
 **/

namespace Koansu\Testing;

use JetBrains\PhpStorm\NoReturn;

use function print_r;

class Debug
{
    #[NoReturn]
    public static function exit(...$args) : void
    {
        self::dump(...$args);
        die();
    }

    public static function dump(...$args) : void
    {
        foreach ($args as $arg) {
            print_r($arg);
        }
    }
}