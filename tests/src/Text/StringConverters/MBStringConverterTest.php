<?php
/**
 *  * Created by mtils on 15.01.2023 at 09:48.
 **/

namespace Koansu\Tests\Text\StringConverters;

use Koansu\Tests\Text\AbstractStringConverterTest;
use Koansu\Text\Contracts\StringConverter;
use Koansu\Text\StringConverters\MBStringConverter;

class MBStringConverterTest extends AbstractStringConverterTest
{
    /**
     * @var string
     **/
    protected $extension = 'mbstring';

    protected $testEveryEncoding = false;

    protected function convert(string $text, string $toEncoding, string $fromEncoding=null) : string
    {
        return mb_convert_encoding($text, $toEncoding);
    }

    protected function newConverter() : StringConverter
    {
        return new MBStringConverter();
    }
}