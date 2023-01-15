<?php
/**
 *  * Created by mtils on 08.01.2023 at 10:35.
 **/

namespace Koansu\Core\Contracts;

/**
 * This interface is for objects that can hold a renderer for later rendering.
 * You may return an object with a default renderer and its __toString just
 * works, but you could also assign a different renderer to render it differently.
 */
interface SelfRenderable
{
    /**
     * Get the assigned renderer (if non was assigned null)
     *
     * @return callable|null
     */
    public function getRenderer() : ?callable;

    /**
     * Set the renderer for this self renderable object.
     *
     * @param callable|null $renderer
     * @return void
     */
    public function setRenderer(?callable $renderer) : void;
}