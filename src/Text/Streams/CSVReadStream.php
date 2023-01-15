<?php
/**
 *  * Created by mtils on 15.01.2023 at 08:30.
 **/

namespace Koansu\Text\Streams;

use Countable;
use Koansu\Core\ConfigurableTrait;
use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Str;
use Koansu\Core\Stream;

use Koansu\Core\Url;
use Koansu\Text\CharsetGuard;
use Koansu\Text\Streams\Helpers\CSVDetector;
use Koansu\Text\Exceptions\DetectionFailedException;
use Koansu\Text\StringConverters\MBStringConverter;
use Koansu\Text\Contracts\StringConverter;

use function array_map;
use function feof;
use function implode;
use function range;
use function str_getcsv;
use function strtoupper;
use function trim;

/**
 * The CsvReadStream is an iterator which allows to read
 * csv files row by row.
 * If you do not set headers or set any separators/delimiters
 * it tries to guess it via the CsvDetector.
 *
 * @sample foreach (new CsvReadStream($file) as $row) ...
 *
 * Definitions used in this class:
 *
 * row: One row of data. The header is excluded, so row[0] is never the header.
 * header: an indexed array of column names.
 * separator: The column separator (,;|)
 * delimiter: The string delimiter if strings contain the separator or \n
 **/
class CSVReadStream extends Stream implements Countable, Configurable
{
    use ConfigurableTrait;

    /**
     * @var string
     **/
    const ENCODING = 'encoding';

    /**
     * @var string
     **/
    protected $separator = '';

    /**
     * @var string
     **/
    protected $delimiter = '"';

    /**
     * @var array
     **/
    protected $header = [];

    /**
     * Caching of header presence
     *
     * @var bool
     **/
    protected $hasHeader = false;

    /**
     * @var bool
     **/
    private $headerRowSkipped = false;

    /**
     * @var bool
     **/
    private $separatorWasDetected = false;

    /**
     * @var bool
     **/
    private $headerWasDetected = false;

    /**
     * @var int
     **/
    protected $startAtRow;

    /**
     * @var CsvDetector
     **/
    protected $detector;

    /**
     * @var string
     **/
    protected $firstLines;

    /**
     * @var string
     **/
    protected $firstLinesRaw;

    /**
     * @var array
     **/
    protected $defaultOptions = [
        self::ENCODING => 'utf-8'
    ];

    /**
     * @var StringConverter
     **/
    protected $stringConverter;

    /**
     * @var bool
     **/
    protected $shouldConvert = false;

    /**
     * @var string
     **/
    protected $convertFrom = '';

    /**
     * @var CharsetGuard
     **/
    protected $charsetGuard;

    /**
     * @param string|Str|Url|resource   $target
     * @param CsvDetector|null          $detector   (optional)
     **/
    public function __construct($target, CsvDetector $detector = null)
    {
        parent::__construct($target, 'r+');
        $this->position = 0;
        $this->setDetector($detector ?: new CsvDetector());
        $this->stringConverter = new MBStringConverter;
    }

    /**
     * Return the column separator
     *
     * @return string
     **/
    public function getSeparator() : string
    {
        if (!$this->separator) {
            $this->separator = $this->detector->separator(
                $this->firstLines(),
                $this->getDelimiter()
            );
            $this->separatorWasDetected = true;
        }

        return $this->separator;
    }

    /**
     * Set the column separator sign
     *
     * @param string $separator
     *
     * @return self
     **/
    public function setSeparator(string $separator) : CSVReadStream
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * Get the string delimiter (for strings containing newlines or separators)
     *
     * @return string
     **/
    public function getDelimiter() : string
    {
        return $this->delimiter;
    }

    /**
     * Set the string delimiter (for strings containing newlines or separators)
     *
     * @param string $delimiter
     *
     * @return self
     **/
    public function setDelimiter(string $delimiter) : CSVReadStream
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * @return CsvDetector
     **/
    public function getDetector() : CSVDetector
    {
        return $this->detector;
    }

    /**
     * @param CsvDetector $detector
     *
     * @return self
     **/
    public function setDetector(CsvDetector $detector) : CSVReadStream
    {
        $this->detector = $detector;
        return $this;
    }

    /**
     * @return StringConverter
     **/
    public function getStringConverter() : StringConverter
    {
        return $this->stringConverter;
    }

    public function setStringConverter(StringConverter $converter) : CSVReadStream
    {
        $this->stringConverter = $converter;
        return $this;
    }

    /**
     * @return CharsetGuard
     **/
    public function getCharsetGuard() : CharsetGuard
    {
        if (!$this->charsetGuard) {
            $this->setCharsetGuard(new CharsetGuard());
        }
        return $this->charsetGuard;
    }

    /**
     * Set the charset guard to better handle encoding errors.
     *
     * @param CharsetGuard $guard
     *
     * @return self
     **/
    public function setCharsetGuard(CharsetGuard $guard) : CSVReadStream
    {
        $this->charsetGuard = $guard;
        return $this;
    }

    /**
     * Return the csv header. A header is an array of column names ['id', 'name'...].
     *
     * @return array
     **/
    public function getHeader() : array
    {
        if ($this->hasHeader) {
            return $this->header;
        }

        $this->updateConversion();

        $detectorException = null;

        try {
            $header = $this->detector->header(
                $this->firstLines(),
                $this->getSeparator(),
                $this->getDelimiter()
            );
        } catch (DetectionFailedException $detectorException) {
            $header = [];
        }

        $this->headerWasDetected = $header && !$this->isNumericHeader($header);


        if ($this->headerWasDetected) {
            $this->setHeader($header);
            return $this->header;
        }

        // If the header could not be detected, check for encoding errors
        // These will be thrown also if the header is not forced
        $this->getCharsetGuard()->forceCharset($this->firstLinesRaw(), $this->convertFrom);

        // If we can get here the charsetGuard did not throw an exception
        if ($detectorException) {
            throw $detectorException;
        }

        $this->setHeader([]);

        return $this->header;
    }

    /**
     * Set the csv file header. A header is an indexed array of column
     * names ['id', 'name'].
     * Set no header or an empty array to let CsvDetector detect the
     * header.
     * If a header was set (count($this->header) the first line
     * will automatically skip.
     *
     * @param array $header
     *
     * @return self
     **/
    public function setHeader(array $header) : CSVReadStream
    {
        $this->header = $header;
        $this->hasHeader = (bool)count($header);
        return $this;
    }

    /**
     * Return true if the header of this csv stream was detected.
     *
     * @return bool
     */
    public function wasHeaderDetected() : bool
    {
        return $this->headerWasDetected;
    }

    /**
     * Return the row count of the file
     *
     * @return int
     **/
    public function count() : int
    {
        $i=0;
        foreach ($this->newCountInstance() as $i=>$row) {
        }
        return $i ? $i+1 : $i;
    }

    /**
     * Read the next row and return it. Skip empty lines.
     *
     * @param resource $handle
     * @param int      $chunkSize
     *
     * @return array|null
     **/
    protected function readNext($handle, int $chunkSize)
    {
        if (feof($handle)) {
            return null;
        }

        $row = $this->readRow($handle, $chunkSize);

        if (!$this->startAtRow && $this->hasHeader && !$this->headerRowSkipped) {
            $this->headerRowSkipped = true;
            return $this->readNext($handle, $chunkSize);
        }

        return $row === [] ? $this->readNext($handle, $chunkSize) : $row;
    }

    /**
     * Read the next row from the file
     *
     * @param resource $handle
     * @param int      $chunkSize (ignored)
     *
     * @return array
     **/
    protected function readRow($handle, int $chunkSize) : array
    {
        $row = str_getcsv($this->readLine($handle, 0), $this->separator, $this->delimiter);

        if ($this->isSkippableRow($row)) {
            return [];
        }

        if (!$this->hasHeader) {
            return array_map( function ($value) { return $this->convertEncoding($value); }, $row);
        }

        $namedRow = [];

        foreach ($this->header as $i=>$column) {
            $namedRow[$column] = $this->convertEncoding($row[$i]);
        }

        return $namedRow;
    }

    /**
     * A skippable row is a (by php) empty row or a row containing no data. This
     * happens often with spreadsheet programs like excel. Here you often have
     * ;;;;;;;;;;;; lines because excel cant determine the end of the file
     * correctly.
     *
     * @param array $row
     *
     * @return bool
     **/
    protected function isSkippableRow(array $row) : bool
    {
        // PHP handles empty LINES (just a \n) with a special array...i love php
        if ( (count($row) == 1) && ($row[0] == null) ) {
            return true;
        }

        // Test if any of the values is not trimmed ""
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Empty first line cache if file did change
     **/
    protected function onRewind()
    {
        $this->headerRowSkipped = false;
        // Trigger detection once
        $this->getHeader();
    }

    /**
     * Return the first lines of the input file
     *
     * @param int $lineCount
     *
     * @return string
     **/
    protected function firstLines(int $lineCount=20) : string
    {
        if ($this->firstLines !== null) {
            return $this->firstLines;
        }

        $this->firstLines = $this->convertEncoding($this->firstLinesRaw($lineCount));

        return $this->firstLines;

    }

    /**
     * Return the first lines of the input file
     *
     * @param int $lineCount
     *
     * @return string
     **/
    protected function firstLinesRaw(int $lineCount=20) : string
    {
        if ($this->firstLinesRaw !== null) {
            return $this->firstLinesRaw;
        }

        $lineReader = $this->newLineReader($this->url());

        $lines = [];

        foreach ($lineReader as $i=>$line) {
            $lines[] = $line;

            if ($i >= $lineCount) {
                break;
            }
        }

        $this->firstLinesRaw = implode("\n", $lines);

        return $this->firstLinesRaw;
    }

    /**
     * Returns if the passed header is a numeric default header of CsvDetector
     * if it can detect the amount of columns but no names
     *
     * @param array $header
     *
     * @return bool
     **/
    protected function isNumericHeader(array $header) : bool
    {
        return $header == range(0, count($header)-1);
    }

    /**
     * Create an instance of this reader just for the line count
     *
     * @return self
     **/
    protected function newCountInstance() : CSVReadStream
    {
        $instance = new static(
            $this->url(),
            $this->getDetector()
        );
        return $instance->setDelimiter($this->getDelimiter())
            ->setSeparator($this->getSeparator())
            ->setHeader($this->getHeader());
    }

    /**
     * Converts encoding if needed.
     *
     * @param string $data
     *
     * @return string
     **/
    protected function convertEncoding(string $data) : string
    {
        if (!$this->shouldConvert) {
            return $data;
        }
        return $this->stringConverter->convert("$data", 'utf-8', $this->getOption(self::ENCODING));
    }

    /**
     * Update the conversion options. This is handled like this for performance
     * reasons.
     **/
    protected function updateConversion() : void
    {
        $this->convertFrom = strtoupper($this->getOption(self::ENCODING));
        $this->shouldConvert = $this->convertFrom != 'UTF-8';
    }

    protected function newLineReader(Url $url) : LineReadStream
    {
        return new LineReadStream($url);
    }
}