<?php
/**
 *  * Created by mtils on 09.10.2022 at 20:35.
 **/

namespace Koansu\Tests;

use Koansu\Filesystem\LocalFilesystem;

use function basename;
use function file_exists;
use function get_class;
use function is_array;
use function is_dir;
use function property_exists;
use function realpath;
use function scandir;
use function str_repeat;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;

use function unlink;

use const DIRECTORY_SEPARATOR;

trait FilesystemMethods
{
    /**
     * @var string[]
     **/
    protected $_createdDirectories = [];

    /**
     * @var string[]
     */
    protected $_createdFiles = [];

    /**
     * Return a new Filesystem instance
     *
     * @return LocalFilesystem
     **/
    protected function newFilesystem()
    {
        return new LocalFilesystem();
    }

    /**
     * Create a return a tempfile and return its path
     *
     * @return string
     **/
    protected function tempFile() : string
    {
        $tempFile =  tempnam(sys_get_temp_dir(), basename(__FILE__));
        $this->_createdFiles[] = $tempFile;
        return $tempFile;
    }

    /**
     * Generate a temp file name and return its path
     *
     * @param string $extension (optional)
     *
     * @return string
     **/
    protected function tempFileName(string $extension='.tmp') : string
    {
        $tempDir = sys_get_temp_dir();
        $prefix = basename(str_replace('\\', '/', get_class($this)));
        return $tempDir.'/'.uniqid("$prefix-").$extension;
    }

    /**
     * Generate a temp dirname and return its name
     *
     * @return string
     **/
    protected function tempDirName() : string
    {
        return $this->tempFileName('');
    }

    /**
     * Create a temporaray directory and return its name
     *
     * @return string
     **/
    protected function tempDir() : string
    {
        $tempDirName = $this->tempDirName();
        $fs = $this->newFilesystem();
        $fs->makeDirectory($tempDirName, 0755, true, true);
        $this->_createdDirectories[] = $tempDirName;
        return $tempDirName;
    }

    /**
     * Outputs the files of a directory
     *
     * @param string $path
     * @param bool $recursive (default:false)
     * @param int $indent (optional)
     */
    protected function dumpDirectory(string $path, bool $recursive=false, int $indent=0): void
    {
        if (!$indent) {
            echo "\nDirectory: $path ----------------------------------";
        }

        $hit = false;

        $indention = str_repeat(' ', $indent);

        foreach (scandir($path) as $file) {

            if ($file == '.' || $file == '..') {
                continue;
            }

            $filePath = realpath("$path/$file");

            if (is_dir($filePath) && $recursive) {
                echo "\n$indention$file/";
                $this->dumpDirectory($filePath, true, $indent+4);
                $hit = true;
                continue;
            }

            $hit = true;
            echo "\n$indention$file";
        }

        if (!$hit) {
            echo "\n$indention(empty)";
        }
    }

    /**
     * Create a directory structure by a nested array. Every string creates a
     * file, every array a directory.
     *
     * @example [
     *     'foo.txt'
     *     'bar.txt',
     *     [
     *         'baz.xml',
     *         'users.json'
     *     ],
     *     'blank.gif'
     * ]
     *
     * @param array  $structure
     * @param array  $pathStructure
     * @param ?string $tempDir (optional)
     *
     * @return array
     **/
    protected function createNestedDirectories(array $structure, array &$pathStructure=[], string $tempDir=null) : array
    {
        $tempDir = $tempDir ? $tempDir : $this->tempDirName();
        $fs = $this->newFilesystem();
        $fs->makeDirectory($tempDir, 0755, true, true);

        foreach ($structure as $name=> $node) {
            $path = "$tempDir/$name";

            if (!is_array($node)) {
                $fs->open($path, 'w')->write('');
                $pathStructure[] = $path;
                continue;
            }

            $fs->makeDirectory($path);
            $pathStructure[] = $path;
            $this->createNestedDirectories($node, $pathStructure, $path);
        }
        $this->_createdDirectories[] = $tempDir;
        return [$tempDir, $pathStructure];
    }

    /**
     * @after
     **/
    protected function purgeTempFiles() : void
    {
        if (!$this->_createdDirectories && !$this->_createdFiles) {
            return;
        }

        if (!$this->shouldPurgeTempFiles()) {
            $this->_createdDirectories = [];
            $this->_createdFiles = [];
            return;
        }

        foreach ($this->_createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $fs = $this->newFilesystem();

        foreach ($this->_createdDirectories as $dir) {
            $fs->delete($dir);
        }

    }

    /**
     * Return if all created directories of this test should be deleted
     * (Just add a property $shouldPurgeTempFiles by default)
     *
     * @return bool
     **/
    protected function shouldPurgeTempFiles() : bool
    {
        if (!property_exists($this, 'shouldPurgeTempFiles')) {
            return true;
        }
        return $this->shouldPurgeTempFiles;
    }

    /**
     * @param string $dir
     * @param array  $results (optional)
     *
     * @return array
     **/
    protected function scandirRecursive(string $dir,  array &$results = []) : array
    {
        $files = scandir($dir);

        foreach($files as $key => $value){
            $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
            if(!is_dir($path)) {
                $results[] = $path;
            } else if($value != "." && $value != "..") {
                getDirContents($path, $results);
                $results[] = $path;
            }
        }

        return $results;
    }
}