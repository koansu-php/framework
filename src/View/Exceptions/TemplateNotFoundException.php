<?php
/**
 *  * Created by mtils on 18.12.2022 at 13:22.
 **/

namespace Koansu\View\Exceptions;

use Koansu\Core\Exceptions\IOException;
use Throwable;

use function implode;

class TemplateNotFoundException extends IOException
{
    /**
     * @var string
     */
    protected $template = '';

    /**
     * @var array
     */
    protected $paths = [];

    public function __construct($view = "", array $paths=[], Throwable $previous = null)
    {
        $this->template = $view;
        $this->paths = $paths;
        parent::__construct($this->createMessage($view, $paths), 4040, $previous);
    }

    /**
     * @return string
     */
    public function getTemplate() : string
    {
        return $this->template;
    }

    /**
     * @param string $template
     * @return TemplateNotFoundException
     */
    public function setTemplate(string $template) : TemplateNotFoundException
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return array
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * @param array $paths
     * @return TemplateNotFoundException
     */
    public function setPaths(array $paths): TemplateNotFoundException
    {
        $this->paths = $paths;
        return $this;
    }


    protected function createMessage(string $template, array $paths) : string
    {
        return "$template not found in paths: " . implode(',',$paths);
    }
}