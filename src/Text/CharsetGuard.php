<?php
/**
 *  * Created by mtils on 15.01.2023 at 11:06.
 **/

namespace Koansu\Text;

use Koansu\Text\Exceptions\InvalidCharsetException;

use Koansu\Text\StringConverters\MBStringConverter;
use Koansu\Text\Contracts\StringConverter as StringConverterContract;

use function chr;
use function implode;
use function preg_match;
use function strlen;
use function strtoupper;
use function substr;

class CharsetGuard
{
    /**
     * @var string
     **/
    const UTF32_BIG_ENDIAN = 'UTF-32BE';

    /**
     * @var string
     **/
    const UTF32_LITTLE_ENDIAN = 'UTF-32LE';

    /**
     * @var string
     **/
    const UTF16_BIG_ENDIAN = 'UTF-16BE';

    /**
     * @var string
     **/
    const UTF16_LITTLE_ENDIAN = 'UTF-16LE';

    /**
     * @var string
     **/
    const UTF8 = 'UTF-8';

    /**
     * @var StringConverterContract
     */
    protected $converter;

    /**
     * This is filled in __construct()
     *
     * @var array
     **/
    protected $byteOrderMarks = [];

    /**
     * @var array
     **/
    protected $defaultDetectOrder = [
        'UTF-8',
        'ISO-8859-1',
        'Windows-1252',
        'ISO-8859-15',
        'Windows-1251',
        'Windows-1250',
        'SJIS'
    ];

    /**
     * @var array
     **/
    protected $detectOrder;

    public function __construct(StringConverterContract $converter=null)
    {
        $this->converter = $converter ?: new MBStringConverter();
        $this->fillByteOrderMarks();
    }

    /**
     * Try to detect the encoding in $string. Strict is just a hint for
     * mb_detect_encoding, the detector usually tries other ways before
     * asking mb_detect_encoding the way you want it to ask.
     *
     * @param string $string
     * @param array  $detectOrder (optional)
     * @param bool   $strict (default:false)
     *
     * @return string
     **/
    public function detect(string $string, array $detectOrder=[], bool $strict=false) : string
    {

        if ($bom = $this->findBOM($string)) {
            return $this->findCharsetByBOM($bom);
        }

        if ($this->isAscii($string)) {
            return 'ASCII';
        }

        if ($this->isUtf8($string)) {
            return 'UTF-8';
        }

        $detectOrder = $detectOrder ?: $this->getDefaultDetectOrder();

        // Passing an array didn't work here, even if doc says...
        return mb_detect_encoding($string, implode(',', $detectOrder), $strict);
    }

    /**
     * Remove th byte order mark from string if set
     *
     * @param string $string
     *
     * @return string
     **/
    public function withoutBOM(string $string) : string
    {
        if ($bom = $this->findBOM($string)) {
            return substr($string, strlen($bom));
        }
        return $string;
    }

    /**
     * Check if $string is in $encoding
     *
     * @param string $string
     * @param string $encoding
     *
     * @return bool
     **/
    public function isCharset(string $string, string $encoding) : bool
    {
        return strtoupper($this->detect($string, [], true)) == strtoupper($encoding);
    }

    /**
     * Throw an exception if $string is not in $encoding
     *
     * @param string $string
     * @param string $encoding
     *
     * @throws InvalidCharsetException
     **/
    public function forceCharset(string $string, string $encoding) : void
    {
        if ($this->isCharset($string, $encoding)) {
            return;
        }

        $e = (new InvalidCharsetException($string, $encoding))->useGuard($this);

        throw $e;
    }

    /**
     * Return true if a string contains only chars of the first 128 chars.
     *
     * @param string $string
     *
     * @return bool
     **/
    public function isAscii(string $string) : bool
    {
        return !preg_match('/[^\x20-\x7f]/', $string);
    }

    /**
     * Return true if a string is utf-8. Returns only true if the
     * string contains special chars.
     *
     * @param string $string
     *
     * @return bool
     *
     * @see https://www.w3.org/International/questions/qa-forms-utf-8.html
     **/
    public function isUtf8(string $string) : bool
    {
        return (bool)preg_match('%(?:
        [\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
        |\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
        |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
        |\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
        |\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
        |[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
        |\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
        )+%xs', $string);
    }

    /**
     * Return the bom vor $type
     *
     * @param string $type
     *
     * @return string
     *
     * @see self::UTF16_BIG_ENDIAN...
     **/
    public function bom(string $type) : string
    {
        return $this->byteOrderMarks[$type];
    }

    /**
     * @return string[]
     */
    public function getDefaultDetectOrder() : array
    {
        if ($this->detectOrder === null) {
            $this->detectOrder = $this->buildDetectOrder();
        }
        return $this->detectOrder;
    }

    /**
     * Filters the detect-order charsets by the support of the system.
     *
     * @return string[]
     */
    protected function buildDetectOrder() : array
    {

        $detectOrder = [];

        foreach ($this->defaultDetectOrder as $i=>$charset) {
            if ($this->converter->canConvert($charset)) {
                $detectOrder[] = $charset;
            }
        }

        return $detectOrder;

    }

    /**
     * Fill the boms with known marks
     **/
    protected function fillByteOrderMarks() : void
    {
        $this->byteOrderMarks = [
            self::UTF32_BIG_ENDIAN      => chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF),
            self::UTF32_LITTLE_ENDIAN   => chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00),
            self::UTF16_BIG_ENDIAN      => chr(0xFE) . chr(0xFF),
            self::UTF16_LITTLE_ENDIAN   => chr(0xFF) . chr(0xFE),
            self::UTF8                  => chr(0xEF) . chr(0xBB) . chr(0xBF)
        ];
    }
    /**
     * Detect the charset by bom.
     *
     * @param string $bom
     *
     * @return string
     **/
    protected function findCharsetByBOM(string $bom) : string
    {
        foreach ($this->byteOrderMarks as $charset=>$knownBom) {
            if ($knownBom === $bom) {
                return $charset;
            }
        }

        return '';

    }

    /**
     * Return the byte order mark of $string. If none return
     * an empty string
     *
     * @param string $string
     *
     * @return string
     **/
    protected function findBOM(string $string) : string
    {

        foreach ($this->byteOrderMarks as $name => $bom) {
            /** @noinspection PhpStrFunctionsInspection */
            if (substr($string, 0, strlen($bom)) === $bom) {
                return $bom;
            }
        }

        return '';
    }
}