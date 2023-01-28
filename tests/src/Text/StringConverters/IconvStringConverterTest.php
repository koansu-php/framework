<?php
/**
 *  * Created by mtils on 15.01.2023 at 10:59.
 **/

namespace Koansu\Tests\Text\StringConverters;

use Koansu\Tests\TestCase;
use Koansu\Tests\Text\AbstractStringConverterTest;

use Koansu\Text\Contracts\StringConverter;
use Koansu\Text\StringConverters\IconvStringConverter;

use function iconv;

class IconvStringConverterTest extends AbstractStringConverterTest
{
    /**
     * @var string
     **/
    protected $extension = 'iconv';

    /**
     * @var bool
     **/
    protected $testEveryEncoding = false;

    protected function convert(string $text, string $toEncoding, string $fromEncoding=null) : string
    {
        $fromEncoding = $fromEncoding ?: 'UTF-8';
        return @iconv($fromEncoding, $toEncoding, $text);
    }

    protected function newConverter() : StringConverter
    {
        return new IconvStringConverter();
    }
}