<?php
/**
 *  * Created by mtils on 21.10.2022 at 10:05.
 **/

namespace Koansu\Tests\Filesystem;

use Koansu\Core\Exceptions\ConcurrencyException;
use Koansu\Core\Str;
use Koansu\Core\Url;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Tests\FilesystemMethods;
use Koansu\Tests\TestCase;

use function array_filter;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function flock;
use function fopen;
use function in_array;
use function mkdir;
use function strlen;
use function strpos;

use const LOCK_EX;
use const LOCK_NB;

class LocalFilesystemTest extends TestCase
{
    use FilesystemMethods;

    /**
     * @test
     */
    public function test_implements_interface()
    {
        $this->assertInstanceOf(
            Filesystem::class,
            $this->newTestFilesystem()
        );
    }

    /**
     * @test
     */
    public function exists_return_true_on_dirs_and_files()
    {
        $fs = $this->newTestFilesystem();
        $this->assertTrue($fs->exists(__FILE__));
        $this->assertTrue($fs->exists(__DIR__));
    }

    /**
     * @test
     */
    public function exists_return_false_if_not_exists()
    {
        $this->assertFalse($this->newTestFilesystem()->exists('foo'));
    }

    /**
     * @test
     **/
    public function open_throws_Exception_if_file_not_found()
    {
        $fs = $this->newTestFilesystem();

        $fs->open('some-not-existing-file.txt');
    }

    /**
     * @test
     */
    public function open_returns_contents_with_file_locking()
    {
        $fs = $this->newTestFilesystem();
        $contentsOfThisFile = file_get_contents(__FILE__);
        $this->assertEquals($contentsOfThisFile, (string)$fs->open(__FILE__)->locked());
    }

    /**
     * @test
     **/
    public function read_throws_exception_when_trying_to_read_a_locked_file()
    {
        $fs = $this->newTestFilesystem();
        $fileName = $this->tempFileName();
        $testString = 'Foo is a buddy of bar';
        $mode = LOCK_EX | LOCK_NB;

        $this->assertEquals(strlen($testString), $fs->open($fileName, 'w')->locked($mode)->write($testString));
        $resource = fopen($fileName, 'a');
        flock($resource, $mode);
        $this->expectException(ConcurrencyException::class);
        $fs->open($fileName)->locked($mode)->__toString();

    }

    /**
     * @test
     */
    public function write_writes_contents_to_file()
    {
        $testString = 'Foo is a buddy of bar';
        $fs = $this->newTestFilesystem();

        $fileName = $this->tempFile();

        $this->assertEquals(strlen($testString), $fs->open($fileName, 'w')->write($testString));
        $this->assertTrue(file_exists($fileName));
        $this->assertEquals($testString, (string)$fs->open($fileName));
        $fs->delete($fileName);
        $this->assertFalse($fs->exists($fileName));
    }

    /**
     * @test
     */
    public function write_writes_stringable_contents_to_file()
    {
        $testString = 'Foo is a buddy of bar';
        $fs = $this->newTestFilesystem();

        $fileName = $this->tempFile();

        $this->assertEquals(strlen($testString), $fs->open($fileName)->write(new Str($testString)));
        $this->assertTrue(file_exists($fileName));
        $this->assertEquals($testString, $fs->open($fileName)->__toString());
        $fs->delete($fileName);
        $this->assertFalse($fs->exists($fileName));
    }

    /**
     * @test
     */
    public function delete_deletes_one_file()
    {
        $fs = $this->newTestFilesystem();
        $tempFile = $this->tempFile();

        $this->assertTrue($fs->exists($tempFile));
        $this->assertTrue($fs->delete($tempFile));
        $this->assertFalse($fs->exists($tempFile));
    }

    /**
     * @test
     */
    public function delete_deletes_many_files()
    {
        $fs = $this->newTestFilesystem();
        $count = 4;
        $tempFiles = [];

        for ($i=0; $i<$count; $i++) {
            $tempFiles[] = $this->tempFile();
        }

        foreach ($tempFiles as $tempFile) {
            $this->assertTrue($fs->exists($tempFile));
        }

        $this->assertTrue($fs->delete($tempFiles));

        foreach ($tempFiles as $tempFile) {
            $this->assertFalse($fs->exists($tempFile));
        }
    }

    /**
     * @test
     */
    public function delete_deletes_one_directory()
    {
        $dirName = $this->tempDirName();

        $fs = $this->newTestFilesystem();

        $this->assertTrue(mkdir($dirName));
        $this->assertTrue($fs->exists($dirName));
        $this->assertTrue($fs->delete($dirName));
        $this->assertFalse($fs->exists($dirName));
    }

    /**
     * @test
     */
    public function delete_deletes_nested_directory()
    {
        $structure = [
            'foo.txt'   => '',
            'bar.txt'   => '',
            'directory' => [
                'baz.xml'    => '',
                'users.json' => '',
                '2016'       => [
                    'gong.doc' => '',
                    'ho.odt'   => ''
                ]
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);
        $fs = $this->newTestFilesystem();
        $this->assertTrue($fs->exists($tempDir));
        $this->assertTrue($fs->delete($tempDir));
        $this->assertFalse($fs->exists($tempDir));
    }

    /**
     * @test
     */
    public function list_directory_lists_paths()
    {
        $fs = $this->newTestFilesystem();
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'baz.txt' => ''
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);
        $listedDirs = $fs->listDirectory($tmpDir);

        sort($listedDirs);
        sort($dirs);
        $this->assertEquals($dirs, $listedDirs);
    }

    /**
     * @test
     */
    public function list_directory_lists_path_recursive()
    {
        $fs = $this->newTestFilesystem();
        $structure = [
            'foo.txt'   => '',
            'bar.txt'   => '',
            'directory' => [
                'baz.xml'    => '',
                'users.json' => '',
                '2016'       => [
                    'gong.doc' => '',
                    'ho.odt'   => ''
                ]
            ]
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);
        $listedDirs = $fs->listDirectory($tmpDir, true);

        sort($listedDirs);
        sort($dirs);

        $this->assertEquals($dirs, $listedDirs);
    }

    /**
     * @test
     */
    public function files_returns_only_files()
    {
        $fs = $this->newTestFilesystem();

        $structure = [
            'foo.txt'   => '',
            'bar.txt'   => '',
            'baz.txt'   => '',
            'directory' => []
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);

        $shouldBe = array_filter($dirs, function ($path) {
            /** @noinspection PhpStrFunctionsInspection */
            return strpos($path, 'directory') === false;
        });

        sort($shouldBe);

        $files = $fs->files($tmpDir);

        sort($files);

        $this->assertEquals($shouldBe, $files);
    }

    /**
     * @test
     */
    public function files_returns_only_files_matching_pattern()
    {
        $fs = $this->newTestFilesystem();

        $structure = [
            'foo.txt'   => '',
            'bar.doc'   => '',
            'baz.txt'   => '',
            'directory' => []
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);

        $shouldBe = array_filter($dirs, function ($path) {
            /** @noinspection PhpStrFunctionsInspection */
            return strpos($path, 'bar.doc') !== false;
        });

        sort($shouldBe);

        $files = $fs->files($tmpDir, '*.doc');

        sort($files);

        $this->assertEquals($shouldBe, $files);
    }

    /**
     * @test
     */
    public function files_returns_only_files_matching_extensions()
    {
        $fs = $this->newTestFilesystem();

        $structure = [
            'foo.txt'     => '',
            'bar.doc'     => '',
            'baz.txt'     => '',
            'hello.gif'   => '',
            'bye.PNG'     => '',
            'doc.doc.pdf' => ''
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);

        $shouldBe = array_filter($dirs, function ($path) {
            /** @noinspection PhpStrFunctionsInspection */
            return strpos($path, 'bar.doc') !== false;
        });

        sort($shouldBe);

        $files = $fs->files($tmpDir, '*', 'doc');

        sort($files);

        $this->assertEquals($shouldBe, $files);

        $shouldBe = array_filter($dirs, function ($path) {
            /** @noinspection PhpStrFunctionsInspection */
            return strpos($path, 'hello.gif') !== false || strpos($path, 'bye.PNG') !== false;
        });

        sort($shouldBe);

        $files = $fs->files($tmpDir, '*', ['gif', 'png']);

        sort($files);

        $this->assertEquals($shouldBe, $files);
    }

    /**
     * @test
     */
    public function directories_returns_only_directories_matching_pattern()
    {
        $fs = $this->newTestFilesystem();

        $structure = [
            'foo'       => [],
            'bar'       => [],
            'bar.txt'   => '',
            'directory' => [],
            'barely'    => []
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);

        $shouldBe = array_filter($dirs, function ($path) {
            /** @noinspection PhpStrFunctionsInspection */
            return strpos($path, 'bar') !== false && strpos($path, 'bar.txt') === false;
        });

        sort($shouldBe);

        $directories = $fs->directories($tmpDir, '*bar*');

        sort($directories);

        $this->assertEquals($shouldBe, $directories);
    }

    /**
     * @test
     */
    public function directories_returns_only_directories()
    {
        $fs = $this->newTestFilesystem();

        $structure = [
            'foo'       => [],
            'bar'       => [],
            'baz.txt'   => '',
            'directory' => []
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);

        $shouldBe = array_filter($dirs, function ($path) {
            /** @noinspection PhpStrFunctionsInspection */
            return strpos($path, 'baz.txt') === false;
        });

        sort($shouldBe);

        $directories = $fs->directories($tmpDir);

        sort($directories);

        $this->assertEquals($shouldBe, $directories);
    }

    /**
     * @test
     */
    public function copy_copies_file()
    {
        $fs = $this->newTestFilesystem();
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'baz.txt' => ''
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);
        $listedDirs = $fs->listDirectory($tmpDir);

        sort($listedDirs);
        sort($dirs);
        $this->assertEquals($dirs, $listedDirs);

        $this->assertFalse($fs->exists("$tmpDir/foo2.txt"));
        $this->assertTrue($fs->copy("$tmpDir/foo.txt","$tmpDir/foo2.txt"));
        $this->assertTrue($fs->exists("$tmpDir/foo.txt"));
        $this->assertTrue($fs->exists("$tmpDir/foo2.txt"));
    }

    /**
     * @test
     */
    public function move_moves_file()
    {
        $fs = $this->newTestFilesystem();
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'baz.txt' => ''
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);
        $listedDirs = $fs->listDirectory($tmpDir);

        sort($listedDirs);
        sort($dirs);
        $this->assertEquals($dirs, $listedDirs);

        $this->assertFalse($fs->exists("$tmpDir/foo2.txt"));
        $this->assertTrue($fs->move("$tmpDir/foo.txt","$tmpDir/foo2.txt"));
        $this->assertFalse($fs->exists("$tmpDir/foo.txt"));
        $this->assertTrue($fs->exists("$tmpDir/foo2.txt"));
    }

    /**
     * @test
     */
    public function link_links_file()
    {
        $fs = $this->newTestFilesystem();
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'baz.txt' => ''
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);
        $listedDirs = $fs->listDirectory($tmpDir);

        sort($listedDirs);
        sort($dirs);
        $this->assertEquals($dirs, $listedDirs);

        $this->assertFalse($fs->exists("$tmpDir/foo2.txt"));
        $this->assertTrue($fs->link("$tmpDir/foo.txt","$tmpDir/foo2.txt"));
        $this->assertTrue($fs->exists("$tmpDir/foo.txt"));
        $this->assertTrue($fs->exists("$tmpDir/foo2.txt"));
        $this->assertEquals(Filesystem::TYPE_FILE, $fs->type("$tmpDir/foo.txt"));
        $this->assertEquals(Filesystem::TYPE_LINK, $fs->type("$tmpDir/foo2.txt"));
    }

    /**
     * @test
     */
    public function url_returns_root()
    {
        $fs = $this->newTestFileSystem();
        $url = $fs->url();
        $this->assertInstanceOf(Url::class, $url);
        $this->assertEquals('file:///', "$url");
    }

    /**
     * @test
     */
    public function size_returns_size()
    {
        $fs = $this->newTestFileSystem();
        $this->assertGreaterThan(10, $fs->size(__FILE__));
    }

    /**
     * @test
     */
    public function supportedTypes_are_not_empty_and_contains_file()
    {
        $fs = $this->newTestFileSystem();
        $this->assertNotEmpty($fs->supportedTypes());
        $this->assertTrue(in_array(Filesystem::TYPE_FILE, $fs->supportedTypes()));
    }

    /**
     * @test
     */
    public function name_returns_only_name()
    {
        $fs = $this->newTestFilesystem();

        $tmpDir = $this->tempDir();
        $fs->open("$tmpDir/foo.txt" ,'w+')->write('foo');
        $this->assertEquals('foo', $fs->name("$tmpDir/foo.txt"));
    }

    /**
     * @test
     */
    public function dirname_returns_only_name()
    {
        $fs = $this->newTestFilesystem();

        $tmpDir = $this->tempDir();
        $fs->open("$tmpDir/foo.txt", 'w+')->write('foo');
        $this->assertTrue($fs->isFile("$tmpDir/foo.txt"));
        $this->assertEquals($tmpDir, $fs->dirname("$tmpDir/foo.txt"));
    }

    /**
     * @test
     */
    public function lastModified_returns_filemtime()
    {
        $fs = $this->newTestFilesystem();
        $this->assertEquals(filemtime(__FILE__), (int)$fs->lastModified(__FILE__)->format('U'));
    }

    /**
     * @test
     */
    public function type_returns_right_type()
    {
        $fs = $this->newTestFilesystem();

        $structure = [
            'foo'       => [],
            'bar'       => [],
            'baz.txt'   => '',
            'directory' => []
        ];

        list($tmpDir, $dirs) = $this->createNestedDirectories($structure);

        unset($dirs);
        $this->assertEquals(Filesystem::TYPE_FILE, $fs->type("$tmpDir/baz.txt"));
        $this->assertEquals(Filesystem::TYPE_DIR, $fs->type("$tmpDir/foo"));
        $this->assertEquals(Filesystem::TYPE_DIR, $fs->type("$tmpDir/bar"));

        $this->assertTrue($fs->makeDirectory("$tmpDir/test"));
        $this->assertEquals(Filesystem::TYPE_DIR, $fs->type("$tmpDir/test"));

    }

    protected function newTestFileSystem(array $args=[]) : Filesystem
    {
        unset($args);
        return $this->newFilesystem();
    }
}
