<?php
/**
 *  * Created by mtils on 09.10.2022 at 21:34.
 **/

namespace Koansu\Filesystem;

use DateTime;
use Koansu\Core\Stream;
use Koansu\Core\Url;
use Koansu\Filesystem\Contracts\Filesystem;
use Throwable;

use function array_filter;
use function array_map;
use function copy;
use function fclose;
use function feof;
use function file_exists;
use function filemtime;
use function filesize;
use function filetype;
use function fnmatch;
use function fread;
use function func_get_args;
use function get_resource_type;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function pathinfo;
use function rename;
use function rmdir;
use function rtrim;
use function strtolower;
use function symlink;
use function unlink;
use function usort;

use const GLOB_BRACE;
use const GLOB_MARK;
use const PATHINFO_BASENAME;
use const PATHINFO_DIRNAME;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;


class LocalFilesystem implements Filesystem
{

    /**
     * @var string
     */
    public static $directoryMimetype = 'inode/directory';

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return bool
     **/
    public function exists(string $path) : bool
    {
        return file_exists($path);
    }

    /**
     * Return the (absolute) url to this filesystem or a path
     * inside it.
     *
     * @param string $path
     *
     * @return Url
     */
    public function url(string $path = '/') : Url
    {
        return new Url("file://$path");
    }


    /**
     * Open a stream to a url.
     *
     * @param Url|string|resource $uri
     * @param string              $mode (default:'r+')
     *
     * @return Stream
     */
    public function open($uri, string $mode='r+') : Stream
    {
        return new Stream(new Url($uri), $mode);
    }


    /**
     * {@inheritdoc}
     *
     * @param string|array $path
     *
     * @return bool
     **/
    public function delete($path) : bool
    {
        $paths = is_array($path) ? $path : func_get_args();

        $success = true;

        foreach ($paths as $path) {
            try {
                if ($this->isDirectory($path)) {
                    if (!$this->deleteDirectoryRecursive($path)) {
                        $success = false;
                    }
                    continue;
                }
                if (!@unlink($path)) {
                    $success = false;
                }
            } catch (Throwable $e) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     **/
    public function copy(string $from, string $to) : bool
    {
        return copy($from, $to);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     **/
    public function move(string $from, string $to) : bool
    {
        return rename($from, $to);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     **/
    public function link(string $from, string $to) : bool
    {
        return symlink($from, $to);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return int
     **/
    public function size(string $path) : int
    {
        return filesize($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return DateTime
     **/
    public function lastModified(string $path) : DateTime
    {
        return DateTime::createFromFormat('U', (string) filemtime($path));
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param bool   $recursive  (optional)
     * @param bool   $withHidden (optional)
     *
     * @return array
     **/
    public function listDirectory(string $path, bool $recursive = false, bool $withHidden = true) : array
    {
        if ($recursive) {
            return $this->listDirectoryRecursive($path);
        }

        $all = $withHidden ? glob($path.'/{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE) : glob("$path/*");

        $all = array_map(function ($path) {
            return rtrim($path, '/\\');
        }, $all);

        sort($all);

        return $all;
    }

    /**
     * {@inheritdoc}
     *
     * @param string       $directory
     * @param string       $pattern    (optional)
     * @param string|array $extensions
     *
     * @return array
     **/
    public function files(string $directory, string $pattern = '*', $extensions = '') : array
    {
        $all = array_filter($this->listDirectory($directory), function ($path) {
            return $this->type($path) == 'file';
        });

        $extensions = $extensions ? (array)$extensions : [];

        return $this->filterPaths($all, $pattern, $extensions);

    }

    /**
     * {@inheritdoc}
     *
     * @param string $directory
     * @param string $pattern   (optional)
     *
     * @return array
     **/
    public function directories(string $directory, string $pattern = '*') : array
    {
        $all = array_filter($this->listDirectory($directory), function ($path) {
            return $this->type($path) == 'dir';
        });

        return $this->filterPaths($all, $pattern);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param int    $mode
     * @param bool   $recursive
     * @param bool   $force
     *
     * @return bool
     **/
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = true, bool $force = false) : bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return bool
     **/
    public function isDirectory(string $path) : bool
    {
        return is_dir($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return bool
     **/
    public function isFile(string $path) : bool
    {
        return is_file($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return string
     **/
    public function name(string $path) : string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return string
     **/
    public function basename(string $path) : string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return string
     **/
    public function dirname(string $path) : string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return string
     **/
    public function extension(string $path) : string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return string
     **/
    public function type(string $path) : string
    {
        return filetype($path);
    }

    /**
     * {@inheritdoc}
     *
     * @return string[]
     */
    public function supportedTypes() : array
    {
        return [
            Filesystem::TYPE_FIFO,
            Filesystem::TYPE_CHAR,
            Filesystem::TYPE_DIR,
            Filesystem::TYPE_BLOCK,
            Filesystem::TYPE_LINK,
            Filesystem::TYPE_FILE,
            Filesystem::TYPE_SOCKET,
            Filesystem::TYPE_UNKNOWN
        ];
    }


    /**
     * @param string $path
     * @return bool
     */
    protected function deleteDirectoryRecursive(string $path) : bool
    {
        if (!$this->isDirectory($path)) {
            return false;
        }

        $all = $this->listDirectory($path, true, true);

        // Sort by path length to resolve hierarchy conflicts
        usort($all, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        $success = true;

        foreach ($all as $nodePath) {
            if ($this->isDirectory($nodePath)) {
                if (!$this->deleteDirectory($nodePath)) {
                    $success = false;
                }
                continue;
            }

            // No directory
            if (!$this->delete($nodePath)) {
                $success = false;
            }
        }

        if (!$this->deleteDirectory($path)) {
            $success = false;
        }

        return $success;
    }

    protected function deleteDirectory(string $path) : bool
    {
        return rmdir($path);
    }

    /**
     * @param string $path
     * @param array $results
     *
     * @return array
     */
    protected function listDirectoryRecursive(string $path, array &$results = []) : array
    {
        foreach ($this->listDirectory(rtrim($path, '/\\')) as $filename) {
            $results[] = $filename;

            if (!$this->isDirectory($filename)) {
                continue;
            }

            $this->listDirectoryRecursive($filename, $results);
        }

        sort($results);

        return $results;
    }

    /**
     * Find out if the passed resource is a stream context resource.
     *
     * @param mixed $resource
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function isStreamContext($resource) : bool
    {
        return is_resource($resource) && get_resource_type($resource) == 'stream-context';
    }

    /**
     * Find out if the passed resource is a stream resource.
     *
     * @param mixed $resource
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function isStream($resource) : bool
    {
        return is_resource($resource) && get_resource_type($resource) == 'stream';
    }

    /**
     * Read from a resource/handle.
     *
     * @param resource $handle
     * @param bool $closeAfter (default:true)
     *
     * @return string
     */
    protected function getFromResource($handle, bool $closeAfter=true) : string
    {
        $contents = '';

        while (!feof($handle)) {
            $contents .= fread($handle, 8192);
        }

        if ($closeAfter) {
            fclose($handle);
        }

        return $contents;
    }

    //<editor-fold desc="FilesystemMethodsTrait">
    /**
     * Filter out file names by pattern and extensions.
     *
     * @param array  $paths
     * @param string $pattern
     * @param array  $extensions
     *
     * @return array
     */
    protected function filterPaths(array $paths, string $pattern, array $extensions=[]) : array
    {
        if (!$this->isFilterPattern($pattern) && !$extensions) {
            return $paths;
        }
        return array_filter($paths, function ($path) use ($pattern, $extensions) {
            return $this->filterPath($path, $pattern, $extensions);
        });
    }

    /**
     * Apply the filter on a single path.
     *
     * @param string $path
     * @param string $pattern
     * @param array  $extensions [optional]
     *
     * @return bool
     */
    protected function filterPath(string $path, string $pattern, array $extensions=[]) : bool
    {
        $usePattern = $this->isFilterPattern($pattern);

        if (!$usePattern && !$extensions) {
            return true;
        }

        if ($usePattern && !fnmatch($pattern, $this->basename($path))) {
            return false;
        }

        if ($extensions && !in_array(strtolower($this->extension($path)), $extensions)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a pattern really should filter.
     *
     * @param string $pattern
     *
     * @return bool
     */
    protected function isFilterPattern(string $pattern) : bool
    {
        return $pattern && $pattern != '*';
    }
    //</editor-fold>
}