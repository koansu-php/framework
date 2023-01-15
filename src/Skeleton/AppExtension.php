<?php
/**
 *  * Created by mtils on 17.12.2022 at 07:04.
 **/

namespace Koansu\Skeleton;

use LogicException;
use Koansu\Routing\Contracts\RouteRegistry;
use Koansu\Routing\Contracts\MiddlewareCollection;

use function array_shift;
use function get_class;
use function is_array;
use function is_object;
use function is_string;
use function spl_object_hash;

abstract class AppExtension
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * Put your bindings here,
     * leads to $app->bind($value, $app->get($key)) !
     *
     * @example [
     *   'MyRouter' => 'RouterInterface',
     *   'MyConfig' => ['ConfigInterface', 'app.config'] // alias
     * ]
     *
     * @var array
     **/
    protected $bindings = [];

    /**
     * Put your singletons here, leads to $app->share($val, $app->get($key)).
     *
     * @see self::bindings
     *
     * @var array
     **/
    protected $singletons = [];

    /**
     * Put your aliases here ([$alias] => [$abstract,$abstract2].
     *
     * @var array
     **/
    protected $aliases = [];

    /**
     * Put any other app extensions into this array. Just the class name
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * A hash table to store which routers were configured.
     *
     * @var array
     */
    private $configuredRegistries = [];

    /**
     * Put a default configuration into this array.
     *
     * @var array
     */
    protected $defaultConfig = [];

    public function __construct(Application $app=null)
    {
        $this->setApp($app);
    }

    public function init() : void
    {
        //
    }

    public function configure() : void
    {

    }

    public function bind() : void
    {

    }

    public function boot() : void
    {

    }

    public function listen() : void
    {

    }

    /**
     * @return Application|null
     */
    public function getApp(): ?Application
    {
        return $this->app;
    }

    /**
     * @param Application|null $app
     */
    public function setApp(?Application $app): void
    {
        if ($app->wasBooted()) {
            throw new LogicException('It makes no sense to assign an already booted application');
        }
        $this->app = $app;
        $app->onBefore(Application::STEP_INIT, function ($app) {
            $this->install($app);
        });

    }

    public function addExtension($extension, array $provides=[]) : void
    {
        $this->extensions[] = $extension;
    }

    /**
     * Overwrite this method to add your routes.
     *
     * @param RouteRegistry $registry
     * @return void
     */
    protected function addRoutes(RouteRegistry $registry) : void
    {
        //
    }

    /**
     * Overwrite this method to add your middleware(s).
     *
     * @param MiddlewareCollection $middlewares
     */
    protected function addMiddleware(MiddlewareCollection $middlewares) : void
    {
        //
    }

    /**
     * Get the absolute path to $path. If is starts with / it is already considered
     * absolute, otherwise it will be translated to $app->path()
     * @param string $path
     * @return string
     */
    protected function absolutePath(string $path) : string
    {
        if ($path[0] == '/') {
            return $path;
        }
        return $this->app->path($path);
    }

    protected function install(Application $app) : void
    {
        if ($app->wasBooted()) {
            throw new LogicException('The passed app was already booted. You cannot register extensions anymore.');
        }

        $this->installExtension($app, $this);
        foreach ($this->extensions as $extension) {
            $extensionObject = is_object($extension) ? $extension : $app->create($extension, ['app' => $app]);
            //$this->installExtension($app, $extensionObject);
        }
        $app->on(Application::STEP_BIND, function () {
            $this->registerAliases();
            $this->bindBindings();
            $this->bindSingletons();
            $this->registerRouteMethod();
        });
    }

    protected function installExtension(Application $app, AppExtension $extension) : void
    {
        $app->on(Application::STEP_INIT, [$extension, 'init']);
        $app->on(Application::STEP_CONFIGURE, [$extension, 'configure']);
        $app->on(Application::STEP_BIND, [$extension, 'bind']);
        $app->on(Application::STEP_BOOT, [$extension, 'boot']);
        $app->on(Application::STEP_LISTEN, [$extension, 'listen']);
    }

    /**
     * Registers all in $this->aliases.
     **/
    protected function registerAliases() : void
    {
        foreach ($this->aliases as $abstract => $aliases) {
            foreach ((array) $aliases as $alias) {
                $this->app->alias($alias, $abstract);
            }
        }
    }

    /**
     * Registers all in $this->bindings.
     **/
    protected function bindBindings() : void
    {
        foreach ($this->bindings as $concrete => $abstracts) {

            if (!is_array($abstracts)) {
                $this->app->bind($abstracts, $concrete);
                continue;
            }

            $first = array_shift($abstracts);

            $this->app->bind($first, $concrete);

            foreach ($abstracts as $abstract) {
                $this->app->alias($first, $abstract);
            }
        }
    }

    /**
     * Registers all in $this->singletons.
     **/
    protected function bindSingletons() : void
    {
        foreach ($this->singletons as $concrete => $abstracts) {
            if (!is_array($abstracts)) {
                $this->app->bind($abstracts, $concrete, true);
                continue;
            }

            $first = array_shift($abstracts);

            $this->app->bind($first, $concrete, true);

            foreach ($abstracts as $abstract) {
                $this->app->alias($first, $abstract);
            }
        }
    }


    /**
     * Get a config for this extension or fallback to $this->defaultConfig.
     *
     * @param string $name
     * @return array
     */
    protected function getConfig(string $name) : array
    {
        if (!$appConfig = $this->app->config($name)) {
            return $this->defaultConfig;
        }
        $config = [];
        foreach ($this->defaultConfig as $key=>$value) {
            $config[$key] = $appConfig[$key] ?? $value;
        }
        return $config;
    }

    protected function registerMiddlewareMethod() : void
    {
        $this->app->onAfter(MiddlewareCollection::class, function (MiddlewareCollection $collection) {
            $this->addMiddleware($collection);
        });
    }

    protected function registerRouteMethod() : void
    {
        $this->app->onAfter(RouteRegistry::class, function (RouteRegistry $registry) {
            $routerId = spl_object_hash($registry);
            if (isset($this->configuredRegistries[$routerId])) {
                return;
            }
            $this->configuredRegistries[$routerId] = true;
            $this->addRoutes($registry);
        });
    }

}