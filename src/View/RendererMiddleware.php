<?php
/**
 *  * Created by mtils on 18.12.2022 at 13:12.
 **/

namespace Koansu\View;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\Contracts\SelfRenderable;
use Koansu\Core\ExtendableTrait;
use Koansu\Core\RenderData;
use Koansu\Core\Response;
use Koansu\Routing\Contracts\Input;

use Koansu\Testing\Debug;

use function xdebug_break;

class RendererMiddleware implements Extendable
{
    use ExtendableTrait;

    /**
     * Create a renderer for $input and $renderData.
     *
     * @param Input $input
     * @param RenderData|null $renderData
     *
     * @return ?callable
     */
    public function renderer(Input $input, RenderData $renderData = null): ?callable
    {
        return $this->callUntilNotNull($this->_extensions, [$input, $renderData]);
    }

    /**
     * Use the factory as middleware.
     *
     * @param Input $input
     * @param callable $next
     *
     * @return Response
     */
    public function __invoke(Input $input, callable $next) : Response
    {
        /** @var Response $response */
        $response = $next($input);
        $payload = $response->payload;

        if (!$payload instanceof SelfRenderable) {
            return $response;
        }

        if ($payload->getRenderer()) {
            return $response;
        }

        $this->assignRenderer($input, $payload);

        return $response;
    }

    /**
     * Create a renderer and assign it to the renderable.
     *
     * @param Input $input
     * @param SelfRenderable $item
     */
    protected function assignRenderer(Input $input, SelfRenderable $item)
    {
        if ($renderer = $this->renderer($input, $item)) {
            $item->setRenderer($renderer);
        }
    }
}