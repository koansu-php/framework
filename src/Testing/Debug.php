<?php
/**
 *  * Created by mtils on 19.12.2022 at 05:44.
 **/

namespace Koansu\Testing;

use JetBrains\PhpStorm\NoReturn;
use Koansu\Core\Str;

use function ob_get_clean;
use function str_replace;
use function strip_tags;
use function var_dump;

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
        echo self::format(...$args);
    }

    public static function format(...$args) : string
    {
        $nl = '';
        ob_start();
        foreach ($args as $arg) {
            echo $nl;
            var_dump(...$args);
            $nl = PHP_EOL;
        }
        $varDumpLine = __LINE__ - 3;
        $xdebugOut = __FILE__ . ":$varDumpLine:";

        $string = trim((string)ob_get_clean());
        $plain = trim(strip_tags($string));
        if (!Str::stringContains(trim($plain), __FILE__)) {
            return $string;
        }
        return str_replace($xdebugOut, '', $string);
    }
}