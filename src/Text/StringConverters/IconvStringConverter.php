<?php
/**
 *  * Created by mtils on 15.01.2023 at 10:42.
 **/

namespace Koansu\Text\StringConverters;

use Koansu\Text\Contracts\StringConverter;
use RuntimeException;

use function exec;
use function function_exists;
use function iconv;
use function iconv_get_encoding;
use function strtoupper;
use function trim;

class IconvStringConverter implements StringConverter
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
        if (!function_exists('iconv_get_encoding')) {
            throw new RuntimeException('iconv extension not found');
        }
        $this->defaultEncoding = iconv_get_encoding('internal_encoding');
    }

    /**
     * {@inheritdoc}
     *
     * @param string $text
     * @param string $outEncoding
     * @param string $inEncoding (optional)
     *
     * @return string
     **/
    public function convert(string $text, string $outEncoding, string $inEncoding = '') : string
    {
        $inEncoding = $inEncoding ?: $this->defaultEncoding;
        return @iconv($inEncoding, $outEncoding, $text);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $encoding
     *
     * @return bool
     **/
    public function canConvert(string $encoding) : bool
    {
        $this->fillEncodingsOnce();

        return isset($this->encodingLookup[strtoupper($encoding)]);
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     **/
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

        foreach ($this->loadEncodings() as $encoding) {
            $encoding = strtoupper($encoding);
            $this->encodings[] = $encoding;
            $this->encodingLookup[$encoding] = true;
        }

        $this->filled = true;
    }

    protected function loadEncodings() : array
    {
        $shellOutput = [];
        exec('iconv -l', $shellOutput);

        $encodings = [];

        foreach ($shellOutput as $line) {
            $encodings[] = trim($line, '/');
        }

        return $encodings;
    }
}