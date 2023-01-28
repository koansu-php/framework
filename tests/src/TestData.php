<?php
/**
 *  * Created by mtils on 08.10.2022 at 08:21.
 **/

namespace Koansu\Tests;

use function file_get_contents;
use function realpath;
use function rtrim;

trait TestData
{
    /**
     * Return the full path to a data file.
     *
     * @param string $file
     * @return bool|string
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected static function dataFile(string $file)
    {
        return realpath(__DIR__."/../data/$file");
    }

    /**
     * Get the contents of a data file
     *
     * @param string $file
     *
     * @return false|string
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected static function dataFileContent(string $file)
    {
        return file_get_contents(static::dataFile($file));
    }

    /**
     * @param string $file
     *
     * @return array
     */
    protected static function includeDataFile(string $file) : array
    {
        return include(static::dataFile($file));
    }

    /**
     * @notest
     *
     * @param string $dir
     * @return string
     */
    protected static function dirOfTests(string $dir='') : string
    {
        return rtrim(realpath(__DIR__."/../../tests/" . $dir),'/');
    }
}