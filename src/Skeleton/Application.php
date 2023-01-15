<?php
/**
 *  * Created by mtils on 01.11.2022 at 07:11.
 **/

namespace Koansu\Skeleton;

use ArrayAccess;
use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\DependencyInjection\Contracts\Container as ContainerContract;
use Koansu\Core\Type;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;
use Koansu\Skeleton\Contracts\InputConnection;
use Koansu\Skeleton\Contracts\OutputConnection;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\DependencyInjection\Container;
use Koansu\Core\ListenerContainer;
use LogicException;
use Traversable;

use function get_class;
use function in_array;
use function is_array;
use function is_callable;
use function is_object;
use function php_sapi_name;

/**
 * This application is a minimal version optimized
 * for flexibility and fewer dependencies.
 * Somewhere in your app directory make this:.
 *
 * $app = (new Application)->setName('My App')
 *                         ->setVersion('2.0.3')
 *                         ->setPath(realpath('../'))
 *
 * There should be only one real singleton: the application. If we could
 * work without any singleton it would be better but that is very clumsy.
 * Globals are bad, and statics (and therefore singletons) are globals so
 * please avoid putting just everything you need into a singleton instance.
 *
 * The only things which are allowed to be put into the application are
 * the things which belong to the application. These are in this case:
 *
 * - bindings (therefore it implements IOCContainer)
 * - config (you configure your Application once)
 * - paths (the static paths of your application)
 *
 * What is meant here: If for example the view directory changes while
 * processing the request, it should not be assigned to config or any singleton.
 * Only the things which will not change after deploying your application
 * should be stored in it.
 *
 * Don't use config, paths or whatever to store temporary values. If values
 * belong to a request or console call, store them in the request because they
 * belong to _this_ request. Then you have to pass your request just everywhere.
 *
 **/
class Application extends Container implements ContainerContract, HasMethodHooks
{
    /**
     * @var string
     */
    public const PRODUCTION = 'production';

    /**
     * @var string
     */
    public const LOCAL = 'local';

    /**
     * @var string
     */
    public const TESTING = 'testing';

    public const STEP_INIT = 'init';

    public const STEP_CONFIGURE = 'configure';

    public const STEP_BIND = 'bind';

    public const STEP_BOOT = 'boot';

    public const STEP_LISTEN = 'listen';

    /**
     * @var string
     **/
    protected $name = '';

    /**
     * @var string
     **/
    protected $version = '';

    /**
     * @var array|ArrayAccess
     */
    protected $config = [];

    /**
     * @var array|ArrayAccess
     */
    protected $paths = [];

    /**
     * @var Url
     **/
    protected $path;

    /**
     * @var Url
     **/
    protected $url;

    /**
     * @var string
     */
    protected $environment = self::PRODUCTION;

    protected $completedSteps = [
        self::STEP_INIT         => false,
        self::STEP_CONFIGURE    => false,
        self::STEP_BIND         => false,
        self::STEP_BOOT         => false,
        self::STEP_LISTEN       => false,
    ];

    /**
     * @var ?Input
     */
    protected $currentInput;

    /**
     * @var static
     */
    protected static $staticInstance;

    /**
     * Application constructor.
     *
     * @param $path
     * @param bool $bindAsApp (default:true)
     */
    public function __construct($path, bool $bindAsApp=true)
    {
        $this->path = new Url($path);
        parent::__construct();

        if ($bindAsApp) {
            $this->instance('app', $this);
        }
        $this->instance(static::class, $this);
    }

    //<editor-fold desc="Getters and Setters">
    /**
     * Return the application name.
     *
     * @return string
     **/
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Set the application name.
     *
     * @param string $name
     *
     * @return self
     **/
    public function setName(string $name) : Application
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Return the application version.
     *
     * @return string
     **/
    public function getVersion() : string
    {
        return $this->version;
    }

    /**
     * Set the application version.
     *
     * @param string
     *
     * @return self
     **/
    public function setVersion(string $version) : Application
    {
        $this->version = $version;

        return $this;
    }
    //</editor-fold>

    //<editor-fold desc="Paths and URLs">

    /**
     * Return the url of the application itself. This is the one single url that
     * never changes. Mostly it is the url of the project working on this app.
     *
     * It is NOT HTTP_HOST.
     *
     * Urls can change during a request, or you need a specific url for a user,
     * or you run a multi virtual host application.
     * So for url generation use other classes but not the application.
     *
     * @return Url
     **/
    public function getUrl() : Url
    {
        return $this->url;
    }

    /**
     * Set the url of the application.
     *
     * @param Url $url
     *
     * @return self
     **/
    public function setUrl(Url $url) : Application
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Return an array(like) of application paths indexed by a name.
     * (e.g. ['public' => $appRoot/public])
     *
     * @return array|ArrayAccess
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Set the application paths.
     *
     * @see self::getPaths()
     *
     * @param array|ArrayAccess $paths
     *
     * @return $this
     */
    public function setPaths(iterable $paths) : Application
    {
        $this->paths = Type::forceAndReturn($paths, ArrayAccess::class);
        return $this;
    }

    /**
     * This is a method to retrieve paths of the application.
     * Without any parameter it returns the root path. This is
     * typically a directory root with a public folder.
     *
     * This path is set in __construct() and cannot be changed.
     *
     * To get any other path, just pass a name or an url.
     *
     *
     * @param string|null $name
     *
     * @return Url
     */
    public function path(string $name=null) : Url
    {
        if (!$name || in_array($name, ['/', '.', 'root', 'app'])) {
            return $this->path;
        }

        list($scope, $path) = $this->splitNameAndPath($name);

        // If no scope was passed, just return an absolute url appended to root path
        if (!$scope) {
            return $this->path->append($path);
        }

        if (!isset($this->paths[$scope])) {
            throw new KeyNotFoundException("No path found with name '$scope'");
        }

        $url = $this->paths[$scope] instanceof Url ? $this->paths[$scope] : new Url($this->paths[$scope]);

        return $path ? $url->append($path) : $url;

    }

    //</editor-fold>

    //<editor-fold desc="Startup Process">
    /**
     * @return bool
     */
    public function wasInitialized() : bool
    {
        return $this->hasCompleted(self::STEP_INIT);
    }

    public function wasConfigured() : bool
    {
        return $this->hasCompleted(self::STEP_CONFIGURE);
    }

    /**
     * @return bool
     */
    public function didBind() : bool
    {
        return $this->hasCompleted(self::STEP_BIND);
    }

    /**
     * @return bool
     */
    public function wasBooted() : bool
    {
        return $this->hasCompleted(self::STEP_BOOT);
    }

    /**
     * @return bool
     */
    public function didListen() : bool
    {
        return $this->hasCompleted(self::STEP_LISTEN);
    }

    /**
     * @param string $step
     * @return bool
     */
    public function hasCompleted(string $step) : bool
    {
        return $this->completedSteps[$step];
    }

    protected function runStep(string $step, array $args=[], array $positions=ListenerContainer::POSITIONS)
    {
        $this->listeners->call($step, $args ?: [$this], $positions);
        if (in_array(ListenerContainer::AFTER, $positions)) {
            $this->completedSteps[$step] = true;
        }
    }

    //</editor-fold>

    //<editor-fold desc="Config & Environment">
    /**
     * Get the application configuration.
     *
     * @return array|ArrayAccess
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set the application configuration.
     *
     * @param array|ArrayAccess $config
     *
     * @return self
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setConfig($config) : Application
    {
        if ($this->wasBooted()) {
            throw new LogicException('You can only set configuration before boot.');
        }
        $this->config = Type::forceAndReturn($config, ArrayAccess::class);
        return $this;
    }

    /**
     * Return a configuration value. Pass nothing and get the complete config.
     * Pass a key, and you get the value of that key, or if it does not exist
     * $default.
     *
     * @param string $key
     * @param mixed $default (optional)
     *
     * @return mixed
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function config(string $key, $default=null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Set a configuration value. To prevent misuse the configuration values can
     * not be changed after booting. Otherwise, somebody will use it as a global
     * variable storage.
     *
     * @param string|array|ArrayAccess $key
     * @param mixed                    $value (optional)
     *
     * @return self
     * @noinspection PhpMissingParamTypeInspection
     */
    public function configure($key, $value=null) : Application
    {
        if ($this->wasBooted()) {
            throw new LogicException('You can only set configuration before boot.');
        }

        if (!is_array($key) && !$key instanceof Traversable && $value !== null) {
            $this->config[$key] = $value;
            return $this;
        }

        foreach ($key as $configKey=>$value) {
            $this->configure($configKey, $value);
        }

        return $this;

    }

    /**
     * Return the application environment.
     *
     * @return string
     */
    public function getEnvironment() : string
    {
        return $this->environment;
    }

    /**
     * Set the application environment.
     *
     * @param string $env
     *
     * @return self
     */
    public function setEnvironment(string $env) : Application
    {
        $this->environment = $env;
        return $this;
    }

    //</editor-fold>

    //<editor-fold desc="Events and Hooks">
    /**
     * @return array
     */
    public function methodHooks() : array
    {
        return [self::STEP_INIT, self::STEP_CONFIGURE, self::STEP_BIND, self::STEP_BOOT, self::STEP_LISTEN];
    }
    //</editor-fold>

    //<editor-fold desc="Listen / Handle Input">

    /**
     * This is a shortcut to read from the input connection
     *
     * @param callable $handler
     *
     * @return void
     *
     * @see InputConnection::read()
     */
    public function listen(callable $handler) : void
    {
        if (!$this->hasCompleted(self::STEP_BOOT)) {
            static::$staticInstance = $this;
            $this->runStep(self::STEP_INIT);
            $this->runStep(self::STEP_CONFIGURE);
            $this->runStep(self::STEP_BIND);
            $this->runStep(self::STEP_BOOT);
        }

        $this->runStep(self::STEP_LISTEN, [$this], [ListenerContainer::BEFORE]);

        /** @var InputConnection $in */
        $in = $this->get(InputConnection::class);
        $this->runStep(self::STEP_LISTEN, [$this, $in], [ListenerContainer::ON]);

        /** @var OutputConnection $out */
        $out = $this->get(OutputConnection::class);

        $in->read(function (Input $input) use ($handler, $out) {
            $this->currentInput = $input;
            $handler($input, $out, $this);
        });

        $this->runStep(self::STEP_LISTEN, [$this, $in, $out], [ListenerContainer::AFTER]);
    }

    /**
     * Get the last input that was passed to the application. Be aware that due
     * immutable input (psr-7) will create new input instances in middlewares, so
     * maybe you must traverse the input by input->next until the last one gets
     * returned
     *
     * @return Input|null
     */
    public function currentInput() : ?Input
    {
        return $this->currentInput;
    }

    //</editor-fold>

    //<editor-fold desc="Static Helpers">

    /**
     * Return the application environment
     * @return string
     */
    public static function env() : string
    {
        return static::$staticInstance->environment;
    }

    /**
     * Alias for Container::get().
     *
     * @param string $binding
     * @return mixed|object
     */
    public static function make(string $binding)
    {
        return static::$staticInstance->get($binding);
    }

    /**
     * @return static|null
     */
    public static function current() : ?Application
    {
        return static::$staticInstance;
    }

    /**
     * Alias for $this->path()
     *
     * @param string|null             $name
     *
     * @return Url
     */
    public static function to(string $name=null) : Url
    {
        return static::$staticInstance->path($name);
    }

    /**
     * Static alias for $this->config(). Unfortunately we cant use the same
     * method name here...
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function setting(string $key, $default=null)
    {
        return static::$staticInstance->config($key, $default);
    }
    //</editor-fold>

    //<editor-fold desc="Internal stuff">
    /**
     * Splits the name and path of a path query.
     *
     * @param $name
     *
     * @return array
     */
    protected function splitNameAndPath($name) : array
    {

        list($start, $end) = strpos($name, '::') ? explode('::', $name) : ['', $name];

        if ($start) {
            return [$start, $end];
        }

        $paths = $this->getPaths();

        // If the name does not exist in the paths, it assumes it's a (file)path.
        if (!isset($paths[$end])) {
            return ['', $end];
        }

        return [$end, ''];

    }
    //</editor-fold>

}