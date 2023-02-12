<?php
/**
 *  * Created by mtils on 18.12.2022 at 13:43.
 **/

namespace Koansu\View\Skeleton;

use Koansu\Console\AnsiRenderer;
use Koansu\Core\RenderData;
use Koansu\Routing\ArgvInput;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\MiddlewareCollection;
use Koansu\Skeleton\AppExtension;
use Koansu\View\PhpRenderer;
use Koansu\View\RendererMiddleware;

use Koansu\View\TemplateFinder;

use function func_num_args;
use function gettype;
use function ltrim;

class ViewExtension extends AppExtension
{
    public function bind(): void
    {
        $this->app->bind(RendererMiddleware::class, function () {
            $factory = new RendererMiddleware();
            $this->addRenderers($factory);
            return $factory;
        }, true);

        $this->app->onAfter(MiddlewareCollection::class, function (MiddlewareCollection $collection) {
            $collection->add('view-renderer', RendererMiddleware::class);
        });
    }

    protected function addRenderers(RendererMiddleware $factory)
    {

        $factory->extend('console-view-renderer', function (Input $input, RenderData $renderData=null) {
            if (!$input instanceof ArgvInput || !$renderData) {
                return null;
            }
            return $this->createConsoleRenderer($input, $renderData);
        });

        if (!$viewConfig = $this->app->config('view')) {
            return;
        }

        foreach ($viewConfig as $name=>$config) {
            $factory->extend($name, function (Input $input, $response) use ($config) {
                if ($input->getClientType() != $config['client-type']) {
                    return null;
                }
                if ($config['backend'] == 'php') {
                    return $this->createPhpRenderer($config)->share([
                        'input' => $input,
                        'user'  => $input->getUser()
                    ]);
                }
                return null;
            });
        }
    }

    /**
     * @param array $config
     * @return PhpRenderer
     */
    protected function createPhpRenderer(array $config) : PhpRenderer
    {
        $paths = [];

        foreach ($config['paths'] as $path) {
            $paths[] = $path[0] == '/' ? $path : APP_ROOT . "/$path";
        }
        $finder = (new TemplateFinder())->setPaths($paths);
        if (isset($config['extension']) && $config['extension']) {
            $finder->setExtension('.'.ltrim($config['extension'], '.'));
        }
        /** @var PhpRenderer $renderer */
        $renderer = $this->app->create(PhpRenderer::class, [$finder]);
        $renderer->setContainer($this->app);
        $renderer->share('app', $this->app);
        return $renderer;
    }

    protected function createConsoleRenderer(Input $input, RenderData $renderData=null) : ?AnsiRenderer
    {
        /** @var AnsiRenderer $renderer */
        $renderer = $this->app->create(AnsiRenderer::class);
        return $renderer;
    }
}