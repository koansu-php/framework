<?php
/**
 *  * Created by mtils on 19.12.2022 at 05:44.
 **/

namespace Koansu\Testing;

use JetBrains\PhpStorm\NoReturn;

use function print_r;

use const PHP_EOL;

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
        $nl = '';
        foreach ($args as $arg) {
            echo $nl;
            print_r($arg);
            $nl = PHP_EOL;
        }
    }
}