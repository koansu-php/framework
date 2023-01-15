<?php
/**
 *  * Created by mtils on 18.12.2022 at 08:09.
 **/

namespace Koansu\Routing\Skeleton;

use Koansu\Core\Serializer;
use Koansu\Core\Serializers\JsonSerializer;
use Koansu\Core\Url;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Filesystem\Storages\SingleFileStorage;
use Koansu\Routing\HttpInput;
use Koansu\Routing\RouteCollector;
use Koansu\Routing\RoutedInputHandler;
use Koansu\Routing\RouteMiddleware;
use Koansu\Routing\SessionHandler\FileSessionHandler;
use Koansu\Routing\SessionMiddleware;
use Koansu\Skeleton\AppExtension;
use Koansu\Routing\ResponseFactory;
use Koansu\Routing\Contracts\ResponseFactory as ResponseFactoryContract;
use Koansu\Routing\FastRoute\FastRouteDispatcher;
use Koansu\Routing\Contracts\Dispatcher;
use Koansu\Routing\MiddlewareCollection;
use Koansu\Routing\Contracts\MiddlewareCollection as MiddlewareCollectionContract;
use Koansu\Routing\UrlGenerator;
use Koansu\Routing\Contracts\UrlGenerator as UrlGeneratorContract;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\RouteRegistry;
use Koansu\Routing\Contracts\RouteRegistry as RouteRegistryContract;
use Psr\Http\Message\RequestInterface;
use UnexpectedValueException;
use Koansu\Core\Contracts\Serializer as SerializerContract;
use Koansu\Routing\Router;
use Koansu\Routing\Contracts\Router as RouterContract;
use Koansu\Routing\CompilableRouter;
use Koansu\Routing\ConsoleDispatcher;
use Koansu\Console\AnsiRenderer;


class RoutingExtension extends AppExtension
{
    protected $singletons = [
        ResponseFactory::class  => ResponseFactoryContract::class
    ];

    protected $bindings = [
        FastRouteDispatcher::class => Dispatcher::class,
        MiddlewareCollection::class => MiddlewareCollectionContract::class,
        UrlGenerator::class         => UrlGeneratorContract::class
    ];

    protected $defaultSessionClients = [
        Input::CLIENT_WEB,
        Input::CLIENT_CMS,
        Input::CLIENT_AJAX,
        Input::CLIENT_MOBILE
    ];

    protected $defaultConfig = [
        'compile'       => false,
        'cache_driver'  => 'file',
        'cache_file'    => 'local/cache/routes.json'
    ];

    public function bind(): void
    {
        $this->app->share(RouteRegistryContract::class, function () {
            return $this->createRegistry();
        });

        $this->app->share(RouterContract::class, function () {
            /** @var Router $router */
            $router = $this->app->create(Router::class);
            $router->createObjectsBy($this->app);
            /** @var RouteRegistry $registry */
            $registry = $this->app->get(RouteRegistryContract::class);
            $router->fillDispatchersBy([$registry, 'fillDispatcher']);
            return $router;
        });

        $this->app->share(Router::class, function () {
            return $this->app->get(RouterContract::class);
        });

        $this->app->bind(CompilableRouter::class, function () {
            return $this->createCompilableRouter();
        });

        $this->app->onAfter(ConsoleDispatcher::class, function (ConsoleDispatcher $dispatcher) {
            $dispatcher->setFallbackCommand('commands');
        });

        $this->app->onAfter(MiddlewareCollectionContract::class, function (MiddlewareCollectionContract $middlewares) {
            $this->addDefaultMiddleware($middlewares);
        });

        $this->app->on(AnsiRenderer::class, function (AnsiRenderer $renderer) {
            /** @var RoutesConsoleView $view */
            $view = $this->app->create(RoutesConsoleView::class);
            $renderer->extend('routes.index', $view);
        });

        $this->app->on(RouteCompileController::class, function (RouteCompileController $controller) {
            $controller->setStorage($this->createCacheStorage($this->getRoutingConfig()));
        });

    }

    protected function addRoutes(RouteRegistryContract $registry): void
    {
        $registry->register(function (RouteCollector $routes) {

            $routes->command(
                'commands',
                ConsoleCommandsController::class.'->index',
                'List all of your console commands.'
            )->argument('?pattern', 'List only commands matching this pattern');

            $routes->command(
                'help',
                ConsoleCommandsController::class.'->show',
                'Show help for one console command.'
            )->argument('command_name', 'The name of the command you need help for.');

            $routes->command(
                'routes',
                RoutesController::class.'->index',
                'List all your routes (and commands)'
            )->argument('?columns', 'What columns to show? v=Verb(Method), p=Pattern, n=Name, c=Clients, s=Scopes, m=Middleware')
                ->option('pattern=*', 'Routes matches this pattern', 'p')
                ->option('client=*', 'Routes of this client types', 'c')
                ->option('name=*', 'Routes with this name', 'n')
                ->option('scope=*', 'Routes of this scope', 's');

            $routes->command(
                'routes:compile',
                [RouteCompileController::class, 'compile'],
                'Optimize routes (compile them into a cache file)'
            );

            $routes->command(
                'routes:compile-status',
                [RouteCompileController::class, 'status'],
                'Check if the rules were compiled'
            );
            $routes->command(
                'routes:clear-compiled',
                [RouteCompileController::class, 'clear'],
                'Clear the compile route cache.'
            );
        });
    }


    /**
     * Create normal router if compilation is not wanted or cache file does not
     * exist.
     *
     * @return RouteRegistry
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    protected function createRegistry() : RouteRegistry
    {
        $config = $this->getRoutingConfig();

        if (!$config['compile']) {
            return $this->app->create(RouteRegistry::class);
        }

        /** @var Filesystem $fileSystem */
        $fileSystem = $this->app->get(Filesystem::class);

        if (!$fileSystem->exists($config['cache_file'])) {
            return $this->app->create(RouteRegistry::class);
        }

        $storage = $this->createCacheStorage($this->getRoutingConfig());
        $compiledData = $storage->__toArray();

        if (!isset($compiledData[RouteRegistry::KEY_VALID])) {
            return $this->app->create(RouteRegistry::class);
        }

        /** @var RouteRegistry $registry */
        $registry = $this->app->create(RouteRegistry::class);
        return $registry->setCompiledData($compiledData);
    }

    /**
     * @return array
     */
    protected function getRoutingConfig() : array
    {
        $config = $this->getConfig('routing');
        if (isset($config['cache_file'])) {
            $config['cache_file'] = $this->absolutePath($config['cache_file']);
        }
        return $config;
    }

    protected function createCacheStorage(array $config) : SingleFileStorage
    {
        if (isset($config['cache_driver']) && $config['cache_driver'] != 'file') {
            throw new UnexpectedValueException('I only support cache files for route cache currently not ' . $config['cache_driver']);
        }

        /** @var Filesystem $fileSystem */
        $fileSystem = $this->app->get(Filesystem::class);
        $serializer = $this->createSerializer($fileSystem->extension($config['cache_file']));

        /** @var SingleFileStorage $storage */
        $storage = $this->app->create(SingleFileStorage::class, [
            'filesystem'    => $fileSystem,
            'serializer'    => $serializer
        ]);
        $storage->setUrl(new Url($config['cache_file']));
        return $storage;
    }

    protected function createSerializer(string $extension) : SerializerContract
    {
        if ($extension == 'json') {
            return (new JsonSerializer())->asArrayByDefault();
        }
        if ($extension == 'phpdata') {
            return new Serializer();
        }

        throw new UnexpectedValueException("Unknown serialize extension '$extension'");
    }

    /** @noinspection PhpIncompatibleReturnTypeInspection */
    protected function createCompilableRouter(RouterContract $base=null) : CompilableRouter
    {
        return $this->app->create(CompilableRouter::class, [
            'router'    => $base ?: $this->app->get(RouterContract::class)
        ]);
    }

    protected function addDefaultMiddleware(MiddlewareCollectionContract $collection)
    {

        $this->addClientTypeMiddleware($collection);
        $this->addRouteScopeMiddleware($collection);
        $this->addSessionMiddleware($collection);
        $this->addRouterToMiddleware($collection);
        $this->addRouteMiddleware($collection);
        $this->addRouteHandlerMiddleware($collection);
    }

    protected function addClientTypeMiddleware(MiddlewareCollectionContract $collection)
    {
        $collection->add('client-type', function (Input $input, callable $next) {

            if ($input->getClientType() || !$input instanceof HttpInput) {
                return $next($input);
            }

            if (php_sapi_name() == 'cli') {
                return $next($input->withClientType(Input::CLIENT_CONSOLE));
            }
            if (!$url = $input->getUrl()) {
                return $next($input->withClientType(Input::CLIENT_WEB));
            }
            if ($url->path->first() == 'api') {
                /** @var RequestInterface|Input $nextInput */
                $nextInput = $input->withClientType(Input::CLIENT_API)
                    ->withUrl($url->shift());
                return $next($nextInput);
            }
            return $next($input->withClientType(Input::CLIENT_WEB));
        });
    }

    protected function addRouteScopeMiddleware(MiddlewareCollectionContract $collection)
    {
        $collection->add('route-scope', function (Input $input, callable $next) {
            if (method_exists($input, 'setRouteScope')) {
                $input->setRouteScope('default');
                return $next($input);
            }
            if (method_exists($input, 'withRouteScope')) {
                $input = $input->withRouteScope('default');
            }
            return $next($input);
        });
    }

    protected function addSessionMiddleware(MiddlewareCollectionContract $collection)
    {
        $config = $this->app->config('session');
        $clientTypes = $config['clients'] ?? $this->defaultSessionClients;
        $collection->add('session', SessionMiddleware::class)->clientType(...$clientTypes);

        if (!$config) {
            return;
        }

        $this->app->on(SessionMiddleware::class, function (SessionMiddleware $middleware) use ($config) {
            $this->configureSessionMiddleware($middleware, $config);
        });
    }

    protected function addRouterToMiddleware(MiddlewareCollectionContract $collection)
    {
        $collection->add('router', RouterContract::class);
    }

    protected function addRouteMiddleware(MiddlewareCollectionContract $collection)
    {
        $collection->add('route-middleware', RouteMiddleware::class);
    }

    protected function addRouteHandlerMiddleware(MiddlewareCollectionContract $collection)
    {
        $collection->add('handle', RoutedInputHandler::class);
    }

    protected function configureSessionMiddleware(SessionMiddleware $middleware, array $config)
    {
        $middleware->extend('files', function () use ($config) {
            $path = $config['path'] ?? $this->absolutePath('local/storage/sessions');
            /** @var FileSessionHandler $handler */
            $handler = $this->app->create(FileSessionHandler::class);
            $handler->setPath($path);
            return $handler;
        });

        if (isset($config['cookie'])) {
            $middleware->setCookieConfig($config['cookie']);
        }
        if (isset($config['driver']) && $config['driver']) {
            $middleware->setDriver($config['driver']);
        }
        if (isset($config['driver']) && $config['driver']) {
            $middleware->setDriver($config['driver']);
        }
        if (isset($config['serverside_lifetime']) && $config['serverside_lifetime']) {
            $middleware->setLifeTime((int)$config['serverside_lifetime']);
        }
    }
}