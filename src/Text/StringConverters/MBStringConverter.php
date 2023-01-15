<?php
/**
 *  * Created by mtils on 15.01.2023 at 09:44.
 **/

namespace Koansu\Text\StringConverters;

use Koansu\Text\Contracts\StringConverter;
use RuntimeException;

use function function_exists;
use function strtoupper;
use function var_dump;

class MBStringConverter implements StringConverter
{
    /**
     * @var array
     **/
    protected $encodings = [];

    /**
     * @var array
     **/
    protected $encodingLookup = [];

    /**
     * @var array
     **/
    protected $defaultEncoding;

    /**
     * @var bool
     **/
    private $filled = false;

    public function __construct()
    {
        if (!function_exists('mb_internal_encoding')) {
            throw new RuntimeException('mbstring extension not found');
        }
        $this->defaultEncoding = mb_internal_encoding();
    }

    public function convert(string $text, string $outEncoding, string $inEncoding = '') : string
    {
        $inEncoding = $inEncoding ?: $this->defaultEncoding;
        if ($inEncoding == $outEncoding || $outEncoding == 'PASS') {
            return $text;
        }
        return mb_convert_encoding($text, $outEncoding, $inEncoding);
    }

    public function canConvert(string $encoding) : bool
    {
        $this->fillEncodingsOnce();
        return isset($this->encodingLookup[strtoupper($encoding)]);
    }

    public function encodings() : array
    {
        $this->fillEncodingsOnce();
        return $this->encodings;
    }

    /**
     * Fill the encodings for faster lookups.
     **/
    protected function fillEncodingsOnce()
    {
        if ($this->filled) {
            return;
        }

        foreach (mb_list_encodings() as $encoding) {
            $encoding = strtoupper($encoding);
            $this->encodings[] = $encoding;
            $this->encodingLookup[$encoding] = true;
        }

        $this->filled = true;
    }
}