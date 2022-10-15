<?php
/**
 *  * Created by mtils on 09.10.2022 at 20:41.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\Exceptions\IOException;
use Koansu\Core\Exceptions\NotReadableException;
use Koansu\Core\Exceptions\NotWritableException;
use Koansu\Core\Stream;
use Koansu\Core\Url;
use Koansu\Tests\FilesystemMethods;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;
use Psr\Http\Message\StreamInterface;

use function file_get_contents;
use function filesize;
use function ftell;
use function substr;

use const SEEK_END;

class StreamTest extends TestCase
{
    use FilesystemMethods;
    use TestData;

    /**
     * @test
     */
    public function implements_interfaces()
    {
        $stream = $this->newStream();
        $this->assertInstanceOf(
            StreamInterface::class,
            $stream
        );
    }

    /**
     * @test
     */
    public function setting_and_getting_chunkSize()
    {
        $reader = $this->newStream();
        $reader->setChunkSize(1024);
        $this->assertEquals(1024, $reader->getChunkSize());
    }

    /**
     * @test
     */
    public function getting_isReadable()
    {
        $tempFile = $this->tempFile();

        $resource = fopen($tempFile, 'r');
        $this->assertTrue($this->newStream($resource)->isReadable());
        @fclose($resource);

        $resource = fopen($tempFile, 'w');

        $this->assertFalse($this->newStream($resource)->isReadable());
        @fclose($resource);

        $resource = fopen($tempFile, 'a+');

        $this->assertTrue($this->newStream($resource)->isReadable());
        @fclose($resource);

    }

    /**
     * @test
     */
    public function getting_isWritable()
    {
        $tempFile = $this->tempFile();

        $resource = fopen($tempFile, 'r');

        $this->assertFalse($this->newStream($resource)->isWritable());
        @fclose($resource);

        $resource = fopen($tempFile, 'w');

        $this->assertTrue($this->newStream($resource)->isWritable());
        @fclose($resource);

        $resource = fopen($tempFile, 'a+');

        $this->assertTrue($this->newStream($resource)->isWritable());
        @fclose($resource);
    }

    /**
     * @test
     */
    public function getting_isAsynchronous()
    {

        $tempFile = $this->tempFile();

        $resource = fopen($tempFile, 'r');

        $this->assertFalse($this->newStream($resource)->isAsynchronous());

        @fclose($resource);

        $resource = fopen($tempFile, 'r');
        $stream = $this->newStream($resource);

        $this->assertFalse($stream->isAsynchronous());

        $stream->makeAsynchronous();

        $this->assertTrue($stream->isAsynchronous());

        @fclose($resource);

        $resource = fopen('data://text/plain;base64,', 'r');

        $this->assertTrue($this->newStream($resource)->isAsynchronous());

        @fclose($resource);
    }

    /**
     * @test
     */
    public function getting_url()
    {

        $tempFile = $this->tempFile();

        $resource = fopen($tempFile, 'r');

        $stream = $this->newStream($resource);

        $url = $stream->url();

        $this->assertInstanceOf(Url::class, $url);

        /** @noinspection PhpUnitMisorderedAssertEqualsArgumentsInspection */
        $this->assertEquals($tempFile, "$url");

        @fclose($resource);

    }

    /**
     * @test
     */
    public function isSeekable()
    {
        $tempFile = $this->tempFile();

        $resource = fopen($tempFile, 'r');

        $stream = $this->newStream($resource);

        $this->assertTrue($stream->isSeekable());

        @fclose($resource);



        $resource = fopen('php://stdin', 'r');

        $stream = $this->newStream($resource);

        $this->assertFalse($stream->isSeekable());

        @fclose($resource);

    }

    /**
     * @test
     */
    public function reads_filled_txt_file_in_chunks()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $resource = fopen($file, 'r');

        $stream = $this->newStream($resource);
        $stream->setChunkSize(1024);

        $chunks = [];

        $fileContent = file_get_contents($file);

        $readContent = '';

        foreach ($stream as $i=>$chunk) {
            $chunks[$i] = $chunk;
            $readContent .= $chunk;
        }

        $this->assertEquals($fileContent, $readContent);
        $this->assertCount(6, $chunks);
        $this->assertFalse($stream->valid());
        $this->assertEquals(-1, $stream->key());

        // A second time to test reading without re-opening
        $chunks2 = [];
        $readContent2 = '';

        foreach ($stream as $i=>$chunk) {
            $chunks2[$i] = $chunk;
            $readContent2 .= $chunk;
        }

        $this->assertEquals($fileContent, $readContent2);
        $this->assertCount(6, $chunks2);
        $this->assertFalse($stream->valid());
        $this->assertEquals(-1, $stream->key());

    }

    /**
     * @test
     */
    public function reads_filled_txt_file_in_toString()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');
        $resource = fopen($file, 'r');

        $stream = $this->newStream($resource);
        $fileContent = file_get_contents($file);

        /** @noinspection PhpUnitMisorderedAssertEqualsArgumentsInspection */
        $this->assertEquals($fileContent, "$stream");

    }

    /**
     * @test
     */
    public function isOpen_returns_right_state()
    {

        $file = static::dataFile('ascii-data-eol-l.txt');
        $resource = fopen($file, 'r');

        $stream = $this->newStream($resource);

        $this->assertTrue(is_resource($stream->resource()));

        $stream->open();
        $this->assertTrue($stream->isOpen());
        $stream->close();
        $this->assertFalse($stream->isOpen());

    }

    /**
     * @test
     */
    public function seek_moves_cursor()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');
        $resource = fopen($file, 'r');

        $stream = $this->newStream($resource);
        $stream->setChunkSize(128);

        $stream->rewind();

        $beginning = $stream->current();

        $this->assertStringStartsWith('Lorem', $beginning);

        $this->assertStringEndsWith('aliquy', $beginning);

        $stream->seek(128);

        $middle = $stream->current();

        $this->assertStringStartsWith('am erat', $middle);

        $this->assertStringEndsWith('takimata ', $middle);

        $stream->seek(0, SEEK_END);

        $this->assertSame('', $stream->current());

        $this->assertEquals(filesize($file), ftell($stream->resource()));

    }

    /**
     * @test
     */
    public function write_file_in_one_row()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');

        $content = file_get_contents($inFile);

        $outFile = $this->tempFile();

        $resource = fopen($outFile, 'w');

        $stream = $this->newStream($resource);

        $this->assertEquals(strlen($content), $stream->write($content));

        $stream->close();

        $this->assertEquals($content, file_get_contents($outFile));

    }

    /**
     * @test
     */
    public function write_file_in_chunks()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');
        $content = file_get_contents($inFile);

        $readStream = $this->newStream(fopen($inFile, 'r'));

        $outFile = $this->tempFile();
        $resource = fopen($outFile, 'w');

        $chunkSize = 256;

        $stream = $this->newStream($resource);
        $stream->setChunkSize($chunkSize);


        foreach ($readStream as $chunk) {
            $this->assertEquals(strlen($chunk), $stream->write($chunk));
        }

        $stream->close();
        $this->assertEquals($content, file_get_contents($outFile));
    }

    /**
     * @test
     */
    public function write_file_by_other_stream()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');
        $content = file_get_contents($inFile);

        $inFileResource = fopen($inFile, 'r');

        $readStream = $this->newStream($inFileResource);

        $outFile = $this->tempFile();

        $outFileResource = fopen($outFile, 'w');

        $chunkSize = 256;

        $stream = $this->newStream($outFileResource);
        $stream->setChunkSize($chunkSize);

        $stream->write($readStream);


        $stream->close();
        $this->assertEquals($content, file_get_contents($outFile));

    }

    /**
     * @test
     */
    public function isLocal()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');
        $inFileResource = fopen($inFile, 'r');

        $stream = $this->newStream($inFileResource);

        $this->assertTrue($stream->isLocal());

        $stream->close();


    }

    /**
     * @test
     */
    public function setTimeout()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');
        $inFileResource = fopen($inFile, 'r');

        $stream = $this->newStream($inFileResource);

        $stream->setTimeout(500);

        $stream->close();


    }

    /**
     * @test
     */
    public function type()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');
        $inFileResource = fopen($inFile, 'r');

        $stream = $this->newStream($inFileResource);

        $this->assertEquals('stream', $stream->type());

        $stream->close();


    }

    /**
     * @test
     */
    public function isTerminalType()
    {
        $inFile = static::dataFile('ascii-data-eol-l.txt');
        $inFileResource = fopen($inFile, 'r');

        $stream = $this->newStream($inFileResource);

        $this->assertFalse($stream->isTerminalType());

        $stream->close();


    }

    /**
     * @test
     */
    public function write_throws_exception_if_not_writable()
    {
        $outFile = $this->tempFile();
        $this->expectException(NotWritableException::class);
        $stream = $this->newStream($outFile, 'r');
        $stream->write('whatever');
    }

    /**
     * @test
     */
    public function lock_and_unlock_file()
    {

        $file = $this->tempFile();
        $resource = fopen($file, 'r+');

        $stream = $this->newStream($resource);

        $this->assertFalse($stream->isLocked());
        $this->assertTrue($stream->lock());
        $this->assertTrue($stream->isLocked());
        $this->assertTrue($stream->unlock());
        $this->assertFalse($stream->isLocked());

    }

    /**
     * @test
     */
    public function type_returns_right_type_even_without_resource()
    {
        $this->assertEquals('stream', $this->newStream()->type());

        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file);
        $stream->setChunkSize(1024);

        $stream->rewind();
        $stream->current();

        $this->assertEquals('stream', $stream->type());

    }

    /**
     * @test
     */
    public function methods_that_need_a_stream_also_work_without_one()
    {
        $stream = new StreamTest_ResourceLessStream();

        $stream->makeAsynchronous();
        $this->assertTrue($stream->isAsynchronous());
        $this->assertFalse($stream->isLocked());
        $this->assertFalse($stream->isTerminalType());
        $this->assertFalse($stream->lock());
        $this->assertFalse($stream->unlock());

        $url = $stream->url();
        $this->assertInstanceOf(Url::class, $url);
        $this->assertEquals("", "$url");


    }

    /**
     * @test
     */
    public function test_getting_url()
    {
        $url = new Url('/tmp');
        $stream = $this->newStream($url);
        $this->assertSame($url, $stream->url());
    }

    /**
     * @test
     */
    public function test_isSeekable()
    {
        $stream = $this->newStream(static::dataFile('ascii-data-eol-l.txt'));
        $stream->open();
        $this->assertTrue($stream->isSeekable());
        $stream->close();
    }

    /**
     * @test
     */
    public function read_chunk()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file);
        $content = "$stream";
        $this->assertEquals(substr($content, 0, 1024), $stream->read(1024));

    }

    /**
     * @test
     */
    public function reads_empty_file()
    {
        $file = static::dataFile('empty.txt');

        $stream = $this->newStream($file);
        $stream->setChunkSize(1024);

        $i=0;
        foreach ($stream as $chunk) {
            $i++;
        }

        $this->assertEquals(1, $i);
        $this->assertSame('', $chunk);

    }

    /**
     * @test
     */
    public function read_complete_string_throws_exception_if_write_only()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file, 'w');
        $this->expectException(NotReadableException::class);
        $stream->__toString();

    }

    /**
     * @test
     */
    public function read_string_in_chunks_throws_exception_if_write_only()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file, 'w');
        $this->expectException(NotReadableException::class);
        foreach ($stream as $chunk) {

        }

    }

    /**
     * @test
     */
    public function read_throws_exception_if_path_not_found()
    {
        $stream = $this->newStream('/foo');
        $this->expectException(IOException::class);
        $stream->read(4096);
    }

    /**
     * @test
     */
    public function size_returns_filesize()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file);
        $this->assertEquals(filesize($file), $stream->getSize());

    }

    /**
     * @test
     */
    public function open_creates_handle()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file);

        $this->assertFalse($stream->isOpen());
        $stream->open();
        $this->assertTrue($stream->isOpen());

    }

    /**
     * @test
     */
    public function seek_throws_exception_if_resource_not_seekable()
    {
        $this->expectException(ImplementationException::class);
        (new StreamTest_ResourceLessStream())->seek(10);
    }

    /**
     * @test
     */
    public function metaData_returns_data()
    {
        $file = static::dataFile('ascii-data-eol-l.txt');

        $stream = $this->newStream($file);
        $stream->setChunkSize(128);

        $stream->rewind();

        $stream->current();

        $metaData = $stream->meta();
        $uri = $stream->meta('uri');
        $this->assertEquals($metaData['uri'], $uri);

    }

    /**
     * @test
     */
    public function mode_returns_mode()
    {
        $stream = new StreamTest_ResourceLessStream();
        $this->assertEquals('r+', $stream->mode());
    }

    /**
     * @test
     */
    public function isLocal_returns_correct_value_is_no_resource_present()
    {

        $stream = new StreamTest_ResourceLessStream();
        $stream->url = new Url('file:///tmp/test.txt');

        $this->assertTrue($stream->isLocal());

        $stream = new StreamTest_ResourceLessStream();
        $stream->url = new Url('https://www.google.de');

        $this->assertFalse($stream->isLocal());

        $stream = new StreamTest_ResourceLessStream();
        $stream->url = new Url();
        $this->assertTrue($stream->isLocal());

    }

    /**
     * @param null $resource $resource
     * @param string $mode
     * @return Stream
     */
    protected function newStream($resource=null, string $mode='r+') : Stream
    {
        return new Stream($resource ?: fopen('data://text/plain;base64,', 'r'), $mode);
    }
}

class StreamTest_Stream extends Stream
{
    public $url = false;

    /**
     * @return Url
     */
    public function url() : Url
    {
        if ($this->url !== false) {
            return $this->url;
        }
        return parent::url();
    }


    /**
     * @param resource $resource
     * @return $this
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }
}

class StreamTest_ResourceLessStream extends StreamTest_Stream
{

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        //
    }

    /**
     * @return resource
     */
    public function resource()
    {
        return null;
    }

}