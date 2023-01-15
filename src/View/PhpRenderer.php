<?php
/**
 *  * Created by mtils on 18.12.2022 at 13:28.
 **/

namespace Koansu\View;

use Koansu\Core\RenderData;
use Koansu\Core\Exceptions\ConfigurationException;

use LogicException;
use Psr\Container\ContainerInterface;

use function array_merge;
use function array_pop;
use function extract;
use function is_array;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function str_replace;

/**
 * This is a simple renderer for parsing php templates.
 */
class PhpRenderer
{
    /**
     * @var TemplateFinder
     */
    protected $fileFinder;

    /**
     * @var array
     */
    protected $extendStack = [];

    /**
     * @var array
     */
    protected $sections = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $shares = [];

    /**
     * @var string
     */
    protected $parentSectionPlaceholder = '__parent__section__$name__';

    /**
     * @var string
     */
    protected $childPlaceHolder = '__child__';

    /**
     * @var string[]
     */
    protected $currentSectionStack = [];

    /**
     * @param TemplateFinder $fileFinder
     */
    public function __construct(TemplateFinder $fileFinder)
    {
        $this->fileFinder = $fileFinder;
    }

    public function render(string $template, array $assign=[]) : string
    {
        return $this->__invoke(new RenderData($template, $assign));
    }

    /**
     * Render the passed item. Just let it be evaluated by php.
     *
     * @param RenderData $item
     * @return string
     */
    public function __invoke(RenderData $item) : string
    {

        $vars = $item->getAssignments();

        if ($this->shares) {
            $vars = array_merge($vars, $this->shares);
        }

        ob_start();
        extract($vars);

        $extendCountBefore = count($this->extendStack);

        include($this->fileFinder->file($item->getTemplate()));
        $result = (string)ob_get_clean();

        if (count($this->extendStack) <= $extendCountBefore) {
            return $result;
        }

        $outerView = array_pop($this->extendStack);
        $parent = $this->render($outerView, $vars);

        return str_replace($this->childPlaceHolder, $result, $parent);
    }

    /**
     * Render another template "around" the current template.
     *
     * @param string $view
     * @return void
     */
    public function extend(string $view) : void
    {
        $this->extendStack[] = $view;
    }

    /**
     * @param string $name
     * @param array $variables
     * @return string
     */
    public function partial(string $name, array $variables=[]) : string
    {
        return $this->render($name, $variables);
    }

    /**
     * Start a section.
     *
     * @param string $name
     * @return void
     */
    public function section(string $name) : void
    {
        echo "\n"; // looks more clean in the output
        $this->currentSectionStack[] = $name;
        $level = ob_get_level();
        ob_start();
        if (isset($this->sections[$name])) {
            $this->sections[$name]['level'] = $level;
            return;
        }
        $this->sections[$name] = [
            'level' => $level,
            'output' => ''
        ];
    }

    /**
     * End a section and return its parsed result.
     *
     * @param string $name
     * @return string
     */
    public function end(string $name) : string
    {
        $result = (string)ob_get_clean();
        if (!isset($this->sections[$name])) {
            throw new LogicException("Ending section $name without ever starting it");
        }
        if (ob_get_level() != $this->sections[$name]['level']) {
            throw new LogicException("Not matching ob level when ending Section $name.");
        }
        $currentSection = array_pop($this->currentSectionStack);
        if ($currentSection != $name) {
            throw new LogicException("Closing section '$name' does not match current section stack $currentSection.");
        }

        if ($this->sections[$name]['output']) {
            $result = str_replace($this->makeParentSectionPlaceHolder($name), $result, $this->sections[$name]['output']);
        }

        $this->sections[$name]['output'] = $result;
        return $result;
    }

    /**
     * Insert the parent section content at this place.
     *
     * @param string $section
     * @return string
     */
    public function parent(string $section='') : string
    {
        return $this->makeParentSectionPlaceHolder($section ?: $this->currentSectionName());
    }

    /**
     * Insert the child template (the one that extends the current) here.
     *
     * @return string
     */
    public function child() : string
    {
        return $this->childPlaceHolder;
    }

    /**
     * Resolve objects by the container.
     *
     * @param string $id
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function get(string $id)
    {
        if ($this->container) {
            return $this->container->get($id);
        }
        throw new ConfigurationException('Container was not assigned to the renderer');
    }

    /**
     * Share a variable between all templates.
     *
     * @param string|array $key
     * @param mixed        $value (optional)
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function share($key, $value=null) : PhpRenderer
    {
        $values = is_array($key) ? $key : [$key => $value];
        foreach ($values as $key=>$value) {
            $this->shares[$key] = $value;
        }
        return $this;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param ContainerInterface $container
     * @return $this
     */
    public function setContainer(ContainerInterface $container) : PhpRenderer
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @return array
     */
    public function getShared(): array
    {
        return $this->shares;
    }

    /**
     * @param string $name
     * @return string
     */
    private function makeParentSectionPlaceHolder(string $name) : string
    {
        return str_replace('$name', $name, $this->parentSectionPlaceholder);
    }

    /**
     * @return string
     */
    protected function currentSectionName() : string
    {
        $count = count($this->currentSectionStack);
        return $count > 0 ? $this->currentSectionStack[$count-1] : '';
    }
}