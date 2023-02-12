<?php
/**
 *  * Created by mtils on 26.10.2022 at 11:31.
 **/

namespace Koansu\Routing\SessionHandler;

use DateTime;
use Koansu\Core\Exceptions\IOException;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Filesystem\LocalFilesystem;
use SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var bool
     */
    protected $wasInitialized = false;

    public function __construct(Filesystem $fs=null)
    {
        $this->fs = $fs ?: new LocalFilesystem();
    }

    /**
     * @param string $path
     * @param string $name
     * @return bool|void
     */
    #[\ReturnTypeWillChange]
    public function open($path, $name)
    {
        $this->setPath($path);
        $this->init();
        return true;
    }

    /**
     * Read the session data.
     *
     * @param string $id
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function read($id) : string
    {
        $this->init();
        $fileName = $this->fileName($id);
        // Got warning in log even with @fopen under PHP 8.1
        if (!$this->fs->exists($fileName)) {
            return '';
        }
        try {
            return (string)$this->fs->open($fileName);
        } catch (IOException $e) {
            return '';
        }

    }

    /**
     * @param $id
     * @param $data
     * @return bool
     */
    public function write($id, $data) : bool
    {
        return $this->fs->open($this->fileName($id), 'w')->locked()->write($data) > 0;
    }

    /**
     * @return bool
     */
    public function close() : bool
    {
        return true;
    }

    /**
     * @param $id
     * @return bool
     */
    public function destroy($id) : bool
    {
        $fileName = $this->fileName($id);
        // Got warning in log even with @fopen under PHP 8.1
        if (!$this->fs->exists($fileName)) {
            return false;
        }
        return $this->fs->delete($fileName);
    }

    /**
     * @param $max_lifetime
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function gc($max_lifetime) : int
    {
        $oldest = new DateTime();
        $oldest->setTimestamp(time()-$max_lifetime);
        $deleted = 0;
        foreach ($this->fs->files($this->path, 'session_*') as $file) {
            if ($this->fs->lastModified($file) < $oldest) {
                $this->fs->delete($file);
                $deleted++;
            }
        }
        return $deleted;
    }


    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return FileSessionHandler
     */
    public function setPath(string $path): FileSessionHandler
    {
        $this->path = $path;
        $this->wasInitialized = false;
        return $this;
    }

    protected function init()
    {
        if ($this->wasInitialized) {
            return;
        }
        if (!$this->fs->isDirectory($this->path)) {
            $this->fs->makeDirectory($this->path, 0777);
            $this->wasInitialized = true;
        }
    }

    protected function fileName(string $sessionId) : string
    {
        return $this->path."/session_$sessionId.szd";
    }
}