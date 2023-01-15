<?php
/**
 *  * Created by mtils on 15.01.2023 at 09:08.
 **/

namespace Koansu\Text\Contracts;

interface StringConverter
{
    /**
     * Convert the passed $text into $toEncoding. Optionally pass an
     * input encoding (defaults to mb_internal_encoding).
     *
     * @param string $text
     * @param string $outEncoding
     * @param string $inEncoding (optional)
     *
     * @return string
     **/
    public function convert(string $text, string $outEncoding, string $inEncoding = '') : string;

    /**
     * Return true if you can convert into (and from) $encoding.
     *
     * @param string $encoding
     *
     * @return bool
     **/
    public function canConvert(string $encoding) : bool;

    /**
     * Return a sequential array of all encoding names.
     *
     * @return string[]
     **/
    public function encodings() : array;
}