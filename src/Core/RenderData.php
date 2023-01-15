<?php
/**
 *  * Created by mtils on 27.10.2022 at 16:42.
 **/

namespace Koansu\Core;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

use Koansu\Core\Contracts\SelfRenderable;
use Koansu\Core\Exceptions\HandlerNotFoundException;

use function call_user_func;
use function property_exists;

/**
 * RenderData is an instruction to render the data contained in this object.
 * It is like a DTO between a controller/command and the renderer.
 *
 * @property string     template The template that should be rendered
 * @property array      assignments All assigned (view) variables
 * @property ?callable  renderer The assigned renderer
 */
class RenderData extends Str implements ArrayAccess, IteratorAggregate, SelfRenderable
{
    use SelfRenderableTrait;

    /**
     * @var array
     */
    protected $assignments = [];

    /**
     * @var string
     */
    protected $template = '';


    public function __construct(string $template = '', array $assignments = [], string $mimeType = 'text/html')
    {
        $this->template = $template;
        $this->assignments = $assignments;
        parent::__construct('', $mimeType);
    }

    public function __get($n)
    {
        switch ($n) {
            case 'template':
                return $this->getTemplate();
            case 'assignments':
                return $this->getAssignments();
            case 'renderer':
                return $this->getRenderer();
        }
    }

    /**
     * @param $n
     * @return bool
     */
    public function __isset($n) : bool
    {
        return property_exists($this, $n);
    }

    public function __set($n, $v)
    {
        switch ($n) {
            case 'template':
                $this->template = $v;
                break;
            case 'assignments':
                $this->assign($v);
                break;
            case 'renderer':
                $this->setRenderer($v);
                break;
        }
    }

    public function getTemplate() : string
    {
        return $this->template;
    }

    public function setTemplate(string $template) : RenderData
    {
        $this->template = $template;
        return $this;
    }

    public function getAssignments() : array
    {
        return $this->assignments;
    }

    public function setAssignments(array $assignments) : RenderData
    {
        $this->assignments = $assignments;
        return $this;
    }

    public function assign($var, $value=null) : RenderData
    {
        if ($value !== null) {
            $this->assignments[$var] = $value;
            return $this;
        }
        foreach ($var as $key=>$value) {
            $this->assignments[$key] = $value;
        }
        return $this;
    }

    /**
     * Check if $offset exists.
     *
     * @param mixed $offset
     *
     * @return bool
     **/
    public function offsetExists($offset) : bool
    {
        return isset($this->assignments[$offset]);
    }

    /**
     * Get value of $offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->assignments[$offset];
    }

    /**
     * Set the value of $offset.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->assignments[$offset] = $value;
    }

    /**
     * Unset $offset.
     *
     * @param mixed $offset
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->assignments[$offset]);
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->assignments);
    }

    public function __toString(): string
    {
        if ($this->raw) {
            return parent::__toString();
        }
        if (!$this->renderer) {
            throw new HandlerNotFoundException("No renderer was assigned to render: $this->template");
        }
        return call_user_func($this->renderer, $this);
    }

}