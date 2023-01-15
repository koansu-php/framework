<?php
/**
 *  * Created by mtils on 18.12.2022 at 13:22.
 **/

namespace Koansu\View;

use Koansu\View\Exceptions\TemplateNotFoundException;

use function array_unshift;
use function str_replace;

use const DIRECTORY_SEPARATOR;

/**
 * A simple class to find template files by a dotted view name syntax.
 */
class TemplateFinder
{
    /**
     * @var array
     */
    protected $paths = [];

    /**
     * @var string
     */
    protected $extension = '.php';

    /**
     * Get the absolute file path to template(view) $name
     *
     * @param string $name
     * @return string
     */
    public function file(string $name) : string
    {
        $template = $this->viewToFile($name);
        foreach ($this->paths as $path) {
            $filePath = $path . "/$template";
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        throw new TemplateNotFoundException($name, $this->paths);
    }

    /**
     * Get all configured paths.
     *
     * @return string[]
     */
    public function getPaths() : array
    {
        return $this->paths;
    }

    /**
     * Configure all template paths.
     *
     * @param array $paths
     * @return $this
     */
    public function setPaths(array $paths) :TemplateFinder
    {
        $this->paths = $paths;
        return $this;
    }

    /**
     * Add a path to the view paths. The new path will be preferred.
     *
     * @param string $path
     * @return $this
     */
    public function addPath(string $path) : TemplateFinder
    {
        array_unshift($this->paths, $path);
        return $this;
    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     * @return TemplateFinder
     */
    public function setExtension(string $extension): TemplateFinder
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * Translate the view name $name (foo.bar) to a file name (foo/bar.php)
     *
     * @param string $name
     * @return string
     */
    protected function viewToFile(string $name) : string
    {
        return str_replace('.', DIRECTORY_SEPARATOR, $name) . $this->extension;
    }
}