<?php
/**
 *  * Created by mtils on 08.10.2022 at 15:09.
 **/

namespace Koansu\Filesystem\Contracts;

use DateTime;
use Koansu\Core\Stream;
use Koansu\Core\Url;

interface Filesystem
{
    /**
     * @var string
     */
    const TYPE_FIFO = 'fifo';

    /**
     * @var string
     */
    const TYPE_CHAR = 'char';

    /**
     * @var string
     */
    const TYPE_DIR = 'dir';

    /**
     * @var string
     */
    const TYPE_BLOCK = 'block';

    /**
     * @var string
     */
    const TYPE_LINK = 'link';

    /**
     * @var string
     */
    const TYPE_FILE = 'file';

    /**
     * @var string
     */
    const TYPE_SOCKET = 'socket';

    /**
     * @var string
     */
    const TYPE_UNKNOWN = 'unknown';

    /**
     * Returns if a path exists.
     *
     * @param string $path
     *
     * @return bool
     **/
    public function exists(string $path): bool;

    /**
     * Return the (absolute) url to this filesystem or a path
     * inside it.
     *
     * @param string $path
     *
     * @return Url
     */
    public function url(string $path = '/'): Url;

    /**
     * Open a stream to a url.
     *
     * @param Url|string|resource $uri
     * @param string $mode (default:'r+')
     *
     * @return Stream
     * @noinspection PhpMissingParamTypeInspection
     */
    public function open($uri, string $mode = 'r+'): Stream;

    /**
     * Delete the path $path. Deletes directories, links and files.
     *
     * @param string|array $path
     *
     * @return bool
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function delete($path): bool;

    /**
     * Copy a file|directory.
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     **/
    public function copy(string $from, string $to): bool;

    /**
     * Move a file/dir.
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     **/
    public function move(string $from, string $to): bool;

    /**
     * Create a (sym)link.
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     **/
    public function link(string $from, string $to): bool;

    /**
     * Returns the file size in bytes.
     *
     * @param string $path
     *
     * @return int
     **/
    public function size(string $path): int;

    /**
     * Returns the last modification test.
     *
     * @param string $path
     *
     * @return DateTime
     **/
    public function lastModified(string $path): DateTime;

    /**
     * Return all names in a directory. Files and dirs.
     *
     * @param string $path
     * @param bool $recursive (optional)
     * @param bool $withHidden (optional)
     *
     * @return string[]
     **/
    public function listDirectory(
        string $path,
        bool $recursive = false,
        bool $withHidden = true
    ): array;

    /**
     * Return all files in $directory. Optionally filter by $pattern.
     * Return only files with extension $extension (optional).
     *
     * @param string $directory
     * @param string $pattern (optional)
     * @param string|array $extensions
     *
     * @return string[]
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function files(
        string $directory,
        string $pattern = '*',
        $extensions = ''
    );

    /**
     * Return all directories in $directory. Optionally filter by $pattern.
     *
     * @param string $directory
     * @param string $pattern (optional)
     *
     * @return string[]
     **/
    public function directories(
        string $directory,
        string $pattern = '*'
    ): array;

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @param bool $force
     *
     * @return bool
     **/
    public function makeDirectory(
        string $path,
        int $mode = 0755,
        bool $recursive = true,
        bool $force = false
    ): bool;

    /**
     * Check if $path is a directory.
     *
     * @param string $path
     *
     * @return bool
     **/
    public function isDirectory(string $path): bool;

    /**
     * Check if $path is a file.
     *
     * @param string $path
     *
     * @return bool
     **/
    public function isFile(string $path): bool;

    /**
     * Extract the filename of $path without its extension.
     *
     * @param string $path
     *
     * @return string
     **/
    public function name(string $path): string;

    /**
     * Extract the filename of $path with its extension.
     *
     * @param string $path
     *
     * @return string
     **/
    public function basename(string $path): string;

    /**
     * Extract the dirname of $path.
     *
     * @param string $path
     *
     * @return string
     **/
    public function dirname(string $path): string;

    /**
     * Return the extension of $path.
     *
     * @param string $path
     *
     * @return string
     **/
    public function extension(string $path): string;

    /**
     * Return the type of $path.
     *
     * @param string $path
     *
     * @return string
     **/
    public function type(string $path): string;

    /**
     * Return the supported file (path) types of this filesystem
     *
     * @return string[]
     * @see self::TYPE_FILE, self::TYPE_DIRECTORY
     *
     */
    public function supportedTypes(): array;

}