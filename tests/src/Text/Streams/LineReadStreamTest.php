<?php
/**
 *  * Created by mtils on 15.01.2023 at 08:40.
 **/

namespace Koansu\Tests\Text\Streams;

use Countable;
use Iterator;
use Koansu\Text\Streams\LineReadStream;
use Koansu\Tests\TestCase;

use Koansu\Tests\TestData;

use function file_get_contents;
use function implode;
use function rtrim;
use function str_replace;

class LineReadStreamTest extends TestCase
{
    use TestData;

    /**
     * @test
     */
    public function implements_interfaces()
    {
        $this->assertInstanceOf(
            Iterator::class,
            $this->newStream()
        );
        $this->assertInstanceOf(
            Countable::class,
            $this->newStream()
        );
    }

    /**
     * @test
     */
    public function reads_filled_txt_file()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $reader = $this->newStream($file);

        $lines = [];

        $fileContent = file_get_contents($file);

        foreach ($reader as $i=>$line) {
            $lines[$i] = $line;
        }

        $nl = function ($string) {
            return str_replace("\r","", $string);
        };

        $this->assertCount(11, $lines);
        $this->assertEquals(rtrim($nl($fileContent), "\n"), implode("\n", $nl($lines)));
        $this->assertFalse($reader->valid());
        $this->assertEquals(-1, $reader->key());

        // A second time to test reading without re-opening
        $lines2 = [];

        foreach ($reader as $i=>$chunk) {
            $lines2[$i] = $chunk;
        }

        $this->assertCount(11, $lines2);
        $this->assertEquals(rtrim($nl($fileContent), "\n"), implode("\n", $lines2));
        $this->assertFalse($reader->valid());
        $this->assertEquals(-1, $reader->key());

    }

    /**
     * @test
     */
    public function count_returns_same_count_as_lines()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $reader = $this->newStream($file);

        $lines = [];

        foreach ($reader as $i=>$line) {
            $lines[$i] = $line;
        }

        $this->assertCount(11, $lines);
        $this->assertCount(11, $reader);


    }

    /**
     * @test
     */
    public function reads_filled_windows_txt_file()
    {
        $file = static::dataFile('ascii-data-eol-w.txt');

        $reader = $this->newStream($file);

        $lines = [];

        $fileContent = file_get_contents($file);

        $readContent = '';

        foreach ($reader as $i=>$line) {
            $lines[$i] = $line;
        }

        $this->assertCount(11, $lines);
        $this->assertEquals(rtrim($fileContent, "\r\n"), implode("\r\n", $lines));

    }

    protected function newStream($path='') : \Koansu\Text\Streams\LineReadStream
    {
        return new \Koansu\Text\Streams\LineReadStream($path);
    }

}