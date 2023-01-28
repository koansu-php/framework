<?php
/**
 *  * Created by mtils on 15.01.2023 at 09:34.
 **/

namespace Koansu\Tests\Text;

use Koansu\Text\StringConverter;
use Koansu\Text\Contracts\StringConverter as StringConverterContract;
use Koansu\Text\StringConverters\IconvStringConverter;
use Koansu\Text\StringConverters\MBStringConverter;
use RuntimeException;

class StringConverterTest extends AbstractStringConverterTest
{
    protected $mbString;

    protected $iconvString;

    protected $testEveryEncoding = false;

    protected function convert(string $text, string $toEncoding, string $fromEncoding=null) : string
    {
        try {
            $mbStringConverter = $this->mbStringConverter();
            if ($mbStringConverter->canConvert($toEncoding)) {
                return $mbStringConverter->convert($text, $toEncoding, $fromEncoding ?: '');
            }
        } catch (RuntimeException $e) {
        }

        return $this->iconvStringConverter()->convert($text, $toEncoding, $fromEncoding ?: '');
    }

    protected function newConverter() : StringConverterContract
    {
        $chain = new StringConverter();

        try {
            $chain->add($this->iconvStringConverter());
        } catch (RuntimeException $e) {
        }

        try {
            $chain->add($this->mbStringConverter());
        } catch (RuntimeException $e) {
        }

        return $chain;
    }

    protected function mbStringConverter() : MBStringConverter
    {
        if (!$this->mbString) {
            $this->mbString = new MBStringConverter();
        }
        return $this->mbString;
    }

    protected function iconvStringConverter() : IconvStringConverter
    {
        if (!$this->iconvString) {
            $this->iconvString = new IconvStringConverter();
        }
        return $this->iconvString;
    }
}