<?php /** @noinspection SpellCheckingInspection */

/**
 *  * Created by mtils on 15.01.2023 at 11:55.
 **/

namespace Koansu\Tests\Text\Streams;

use Countable;
use Iterator;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;
use Koansu\Text\Contracts\StringConverter;
use Koansu\Text\Exceptions\DetectionFailedException;
use Koansu\Text\Exceptions\InvalidCharsetException;
use Koansu\Text\Streams\CSVReadStream;
use Koansu\Text\Streams\Helpers\CSVDetector;

class CSVReadStreamTest extends TestCase
{
    use TestData;

    /**
     * @test
     */
    public function implements_interfaces()
    {
        $this->assertInstanceOf(
            Iterator::class,
            $this->newReader()
        );
        $this->assertInstanceOf(
            Countable::class,
            $this->newReader()
        );
    }

    /**
     * @test
     */
    public function getSeparator_detects_separator_if_non_set()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-normalized.csv'));
        $this->assertEquals('|', $reader->getSeparator());
    }

    /**
     * @test
     */
    public function getSeparator_returns_set_separator()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-normalized.csv'));
        $this->assertSame($reader, $reader->setSeparator(';'));
        $this->assertEquals(';', $reader->getSeparator());
    }

    /**
     * @test
     */
    public function setDelimiter_sets_delimiter()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-normalized.csv'));
        $this->assertSame($reader, $reader->setDelimiter("'"));
        $this->assertEquals("'", $reader->getDelimiter());
    }

    /**
     * @test
     */
    public function getDetector_returns_Detector()
    {
        $this->assertInstanceOf(CSVDetector::class, $this->newReader()->getDetector());
    }

    /**
     * @test
     */
    public function getHeader_detects_header_if_not_set()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-normalized.csv'));
        $this->assertEquals(
            ['id', 'name', 'last_name', 'age', 'street'],
            $reader->getHeader()
        );
    }

    /**
     * @test
     */
    public function getHeader_returns_set_header()
    {
        $reader = $this->newReader();
        $this->assertSame($reader, $reader->setHeader(['id', 'name']));
        $this->assertEquals(['id', 'name'], $reader->getHeader());
    }

    /**
     * @test
     */
    public function getStringConverter_returns_converter()
    {
        $reader = $this->newReader();
        $this->assertInstanceOf(StringConverter::class, $reader->getStringConverter());
    }

    /**
     * @test
     */
    public function read_simple_csv_file()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-normalized.csv'));

        $result = [];

        foreach ($reader as $row) {
            $result[] = $row;
        }

        $awaited = [
            [
                'id'        => '42',
                'name'      => 'Talent',
                'last_name' => 'Billy',
                'age'       => '35',
                'street'    => 'Elm Street'
            ],
            [
                'id'        => '52',
                'name'      => 'Duck',
                'last_name' => 'Donald',
                'age'       => '8',
                'street'    => 'Duckcity'
            ]
        ];
        $this->assertEquals($awaited, $result);

    }

    /**
     * @test
     */
    public function read_simple_csv_file_when_no_header_set()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-no-header.csv'));

        $result = [];

        foreach ($reader as $row) {
            $result[] = $row;
        }

        $awaited = [
            [
                0        => '42',
                1      => 'Talent',
                2 => 'Billy',
                3       => '35',
                4    => 'Elm Street'
            ],
            [
                0        => '52',
                1      => 'Duck',
                2   => 'Donald',
                3       => '8',
                4    => 'Duckcity'
            ]
        ];

        $this->assertEquals($awaited, $result);

    }

    /**
     * @test
     * @noinspection PhpUndefinedVariableInspection
     */
    public function read_country_csv_file()
    {
        $reader = $this->newReader(static::dataFile('Countries-ISO-3166-2.csv'));

        $firstRowShouldBe = [
            'Sort Order'               => '1',
            'Common Name'              => 'Afghanistan',
            'Formal Name'              => 'Islamic State of Afghanistan',
            'Type'                     => 'Independent State',
            'Sub Type'                 => '',
            'Sovereignty'              => '',
            'Capital'                  => 'Kabul',
            'ISO 4217 Currency Code'   => 'AFN',
            'ISO 4217 Currency Name'   => 'Afghani',
            'ITU-T Telephone Code'     => '93',
            'ISO 3166-1 2 Letter Code' => 'AF',
            'ISO 3166-1 3 Letter Code' => 'AFG',
            'ISO 3166-1 Number'        => '4',
            'IANA Country Code TLD'    => '.af'
        ];

        $lastRowShouldBe = [
            'Sort Order'               => '272',
            'Common Name'              => 'British Antarctic Territory',
            'Formal Name'              => '',
            'Type'                     => 'Antarctic Territory',
            'Sub Type'                 => 'Overseas Territory',
            'Sovereignty'              => 'United Kingdom',
            'Capital'                  => '',
            'ISO 4217 Currency Code'   => '',
            'ISO 4217 Currency Name'   => '',
            'ITU-T Telephone Code'     => '',
            'ISO 3166-1 2 Letter Code' => 'AQ',
            'ISO 3166-1 3 Letter Code' => 'ATA',
            'ISO 3166-1 Number'        => '10',
            'IANA Country Code TLD'    => '.aq'
        ];

        $firstRow = [];

        foreach ($reader as $i=>$row) {
            if ($i==0) {
                $firstRow = $row;
            }
        }

        $this->assertEquals($firstRowShouldBe, $firstRow);
        $this->assertEquals($lastRowShouldBe, $row);
        $this->assertEquals(268, $i+1);

    }

    /**
     * @test
     * @noinspection PhpUndefinedVariableInspection
     */
    public function read_country_csv_file_with_skippable_lines()
    {
        $reader = $this->newReader(static::dataFile('Countries-ISO-3166-2-semicolon-blank-lines.csv'));

        $firstRowShouldBe = [
            'Sort Order'               => '1',
            'Common Name'              => 'Afghanistan',
            'Formal Name'              => 'Islamic State of Afghanistan',
            'Type'                     => 'Independent State',
            'Sub Type'                 => '',
            'Sovereignty'              => '',
            'Capital'                  => 'Kabul',
            'ISO 4217 Currency Code'   => 'AFN',
            'ISO 4217 Currency Name'   => 'Afghani',
            'ITU-T Telephone Code'     => '93',
            'ISO 3166-1 2 Letter Code' => 'AF',
            'ISO 3166-1 3 Letter Code' => 'AFG',
            'ISO 3166-1 Number'        => '4',
            'IANA Country Code TLD'    => '.af'
        ];

        $lastRowShouldBe = [
            'Sort Order'               => '272',
            'Common Name'              => 'British Antarctic Territory',
            'Formal Name'              => '',
            'Type'                     => 'Antarctic Territory',
            'Sub Type'                 => 'Overseas Territory',
            'Sovereignty'              => 'United Kingdom',
            'Capital'                  => '',
            'ISO 4217 Currency Code'   => '',
            'ISO 4217 Currency Name'   => '',
            'ITU-T Telephone Code'     => '',
            'ISO 3166-1 2 Letter Code' => 'AQ',
            'ISO 3166-1 3 Letter Code' => 'ATA',
            'ISO 3166-1 Number'        => '10',
            'IANA Country Code TLD'    => '.aq'
        ];

        $firstRow = [];

        foreach ($reader as $i=>$row) {
            if ($i==0) {
                $firstRow = $row;
            }
        }

        $this->assertEquals($firstRowShouldBe, $firstRow);
        $this->assertEquals($lastRowShouldBe, $row);
        $this->assertEquals(268, $i+1);

    }

    /**
     * @test
     */
    public function read_with_different_encoding()
    {
        $reader = $this->newReader(static::dataFile('simple-semicolon-placeholder-iso.csv'));

        $reader->setOption('encoding', 'iso-8859-1');

        $this->assertEquals(
            ['id', 'Völlig bekloppte Spalte (nur so)', 'last_name', 'age', 'street'],
            $reader->getHeader()
        );

        $awaited = [
            [
                'id'                               => '42',
                'Völlig bekloppte Spalte (nur so)' => 'Talent',
                'last_name'                        => 'Ängelbärt',
                'age'                              => '35',
                'street'                           => 'Elm Street'
            ],
            [
                'id'                               => '52',
                'Völlig bekloppte Spalte (nur so)' => 'Duck',
                'last_name'                        => 'Tönjes',
                'age'                              => '8',
                'street'                           => 'Duckcity'
            ]
        ];

        $result = [];

        foreach ($reader as $row) {
            $result[] = $row;
        }

        $this->assertEquals($awaited, $result);

    }

    /**
     * @test
     */
    public function read_with_wrong_encoding_throws_InvalidCharsetException()
    {

        $reader = $this->newReader(static::dataFile('simple-semicolon-placeholder-iso.csv'));
        /** @noinspection PhpParamsInspection */
        $reader->setDetector((new CSVDetector())->setOption(CsvDetector::FORCE_HEADER_LINE, true));

        $this->expectException(InvalidCharsetException::class);
        $this->assertEquals(
            ['id', 'Völlig bekloppte Spalte (nur so)', 'last_name', 'age', 'street'],
            $reader->getHeader()
        );

        $awaited = [
            [
                'id'                               => '42',
                'Völlig bekloppte Spalte (nur so)' => 'Talent',
                'last_name'                        => 'Ängelbärt',
                'age'                              => '35',
                'street'                           => 'Elm Street'
            ],
            [
                'id'                               => '52',
                'Völlig bekloppte Spalte (nur so)' => 'Duck',
                'last_name'                        => 'Tönjes',
                'age'                              => '8',
                'street'                           => 'Duckcity'
            ]
        ];

        $result = [];

        foreach ($reader as $row) {
            $result[] = $row;
        }

        $this->assertEquals($awaited, $result);

    }

    /**
     * @test
     */
    public function read_undetectable_header_throws_DetectionFailedException()
    {

        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-no-header.csv'));
        /** @noinspection PhpParamsInspection */
        $reader->setDetector((new CsvDetector())->setOption(CsvDetector::FORCE_HEADER_LINE, true));
        $this->expectException(DetectionFailedException::class);
        $reader->getHeader();

    }

    /**
     * @test
     */
    public function count_returns_count_of_simple_file()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-normalized.csv'));
        $this->assertCount(2, $reader);

    }

    /**
     * @test
     */
    public function count_returns_count_of_simple_file_without_header()
    {
        $reader = $this->newReader(static::dataFile('simple-pipe-placeholder-no-header.csv'));
        $this->assertCount(2, $reader);

    }

    /**
     * @test
     */
    public function count_returns_count_of_country_file()
    {
        $reader = $this->newReader(static::dataFile('Countries-ISO-3166-2.csv'));
        $this->assertCount(268, $reader);
    }

    /**
     * @test
     */
    public function count_returns_count_of_country_file_with_skippable_lines()
    {
        $reader = $this->newReader(static::dataFile('Countries-ISO-3166-2-semicolon-blank-lines.csv'));
        $this->assertCount(268, $reader);
    }

    protected function newReader($path='') : CSVReadStream
    {
        return new CSVReadStream($path);
    }
}