<?php
/**
 *  * Created by mtils on 26.10.2022 at 11:27.
 **/

namespace Koansu\Routing\Psr;

use Koansu\Routing\Contracts\Input;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class PsrAsKoansu
{
    /**
     * @var MiddlewareInterface|null
     */
    protected $psrMiddleware;

    public function __invoke(Input $input, callable $next)
    {
        if (!$input instanceof ServerRequestInterface || !$this->psrMiddleware) {
            return $next($input);
        }
        return $this->psrMiddleware->process($input, new RequestHandler($next));
    }

    public function getPsrMiddleware() : ?MiddlewareInterface
    {
        return $this->psrMiddleware;
    }
}