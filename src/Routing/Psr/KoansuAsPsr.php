<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:53.
 **/

namespace Koansu\Routing\Psr;

use Koansu\Routing\Contracts\InputHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use TypeError;

use function call_user_func;

class KoansuAsPsr implements MiddlewareInterface
{
    /**
     * @var callable|InputHandler
     */
    protected $koansuMiddleware;

    /**
     * @param callable|InputHandler|null $koansuMiddleware
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct($koansuMiddleware=null)
    {
        $this->koansuMiddleware = $koansuMiddleware;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $nextHandler = function ($request) use ($handler) {
            $handler->handle($request);
        };
        if ($this->koansuMiddleware instanceof InputHandler) {
            return $this->koansuMiddleware->__invoke($request);
        }
        return call_user_func($this->koansuMiddleware, $request, $nextHandler);
    }

    public function getKoansuMiddleware() : ?callable
    {
        return $this->koansuMiddleware;
    }

    public function setKoansuMiddleware($koansuMiddleware) : KoansuAsPsr
    {
        if (!is_callable($koansuMiddleware) && !$koansuMiddleware instanceof InputHandler) {
            throw new TypeError('Middleware must be callable or ' . InputHandler::class . ' not ' . get_class($koansuMiddleware));
        }
        $this->koansuMiddleware = $koansuMiddleware;
        return $this;
    }
}