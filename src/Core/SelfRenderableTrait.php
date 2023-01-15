<?php
/**
 *  * Created by mtils on 26.12.2022 at 07:04.
 **/

namespace Koansu\Core;

use Koansu\Core\Contracts\SelfRenderable;
use Koansu\Core\Exceptions\HandlerNotFoundException;

use function call_user_func;
use function get_class;

/**
 * @see SelfRenderable
 */
trait SelfRenderableTrait
{
    /**
     * @var callable
     */
    protected $renderer;

    /**
     * Get the assigned renderer or null if none was assigned.
     *
     * @return callable|null
     */
    public function getRenderer() : ?callable
    {
        return $this->renderer;
    }

    /**
     * Assign a renderer.
     *
     * @param callable|null $renderer
     * @return void
     */
    public function setRenderer(?callable $renderer) : void
    {
        $this->renderer = $renderer;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        if (!$this->renderer) {
            throw new HandlerNotFoundException("No renderer was assigned to render: " . get_class($this));
        }
        return call_user_func($this->renderer, $this);
    }

    protected function renderIfRenderer()
    {
        if (!$this->renderer) {
            return null;
        }
        return call_user_func($this->renderer, $this);
    }

}