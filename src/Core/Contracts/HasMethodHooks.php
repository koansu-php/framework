<?php

namespace Koansu\Core\Contracts;

/**
 * If your object supports hooks, implement this interface.
 **/
interface HasMethodHooks extends Hookable
{
    /**
     * Return an array of method names which can be hooked via
     * onBefore and onAfter.
     *
     * @return string[]
     **/
    public function methodHooks() : array;
}
