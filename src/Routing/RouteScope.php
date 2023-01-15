<?php
/**
 *  * Created by mtils on 23.10.2022 at 19:40.
 **/

namespace Koansu\Routing;

class RouteScope
{
    public const DEFAULT = 'default';

    /**
     * @var string
     */
    protected $pattern = '';

    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return $this->pattern;
    }
}