<?php
/**
 *  * Created by mtils on 17.12.2022 at 08:49.
 **/

namespace Koansu\Skeleton;

use InvalidArgumentException;
use Koansu\Config\Config;
use Koansu\Config\Env;

use Koansu\Config\Processors\ConfigVariablesParser;

use Koansu\Config\Readers\IniFileReader;

use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Skeleton\Contracts\InputConnection;

use Koansu\Skeleton\Contracts\OutputConnection;

use Psr\Log\LoggerInterface;

use function array_key_exists;
use function class_exists;
use function file_exists;
use function file_get_contents;
use function getenv;
use function is_string;
use function iterator_to_array;
use function json_decode;
use function pathinfo;

use const PATHINFO_EXTENSION;

class SkeletonExtension extends AppExtension
{
    /**
     * @var string[]
     */
    protected $configPaths = [];

    protected $envPath = '';

    public function init(): void
    {
        /** @var ErrorHandler $errorHandler */
        $errorHandler = $this->app->create(ErrorHandler::class);
        $this->app->instance(ErrorHandler::class, $errorHandler);
        $errorHandler->install();
    }

    public function configure(): void
    {
        if (!$this->configPaths && !$this->envPath) {
            return;
        }
        // No config installed
        if (!class_exists(Env::class)) {
            return;
        }

        if (!$this->configPaths) {
            $this->loadEnvFileIfExists($this->envPath);
            return;
        }
        $this->app->setConfig($this->createConfig($this->configPaths, $this->envPath));

    }

    public function bind(): void
    {
        $this->app->on(SupportsCustomFactory::class, function (SupportsCustomFactory $object) {
            $object->createObjectsBy($this->app);
        });

        $this->bindInputOutput();

    }

    /**
     * @return string[]
     */
    public function getConfigPaths(): array
    {
        return $this->configPaths;
    }

    /**
     * @param string[] $configPaths
     * @return SkeletonExtension
     */
    public function setConfigPaths(array $configPaths): SkeletonExtension
    {
        $this->configPaths = $configPaths;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnvPath(): string
    {
        return $this->envPath;
    }

    /**
     * @param string $envPath
     * @return SkeletonExtension
     */
    public function setEnvPath(string $envPath): SkeletonExtension
    {
        $this->envPath = $envPath;
        return $this;
    }

    protected function install(Application $app): void
    {
        $app->onBefore(Application::STEP_BIND, function () {
            $this->applyEnv();
            $this->freezeConfig();
        });
        parent::install($app);
    }

    protected function bindInputOutput()
    {
        /** @var IO $io */
        $io = $this->app->create(IO::class);
        $this->app->instance(IO::class, $io);

        if ($config = $this->app->config('logging')) {
            $io->extend('default-logger', function ($channel) use ($config) {
                if ($channel == 'log') {
                    return $this->createLogger($config);
                }
            });
        }

        $this->app->bind(InputConnection::class, function () use ($io) {
            return $io->in();
        });
        $this->app->bind(OutputConnection::class, function () use ($io) {
            return $io->out();
        });
        $this->app->bind(LoggerInterface::class, function () use ($io) {
            return $io->logger();
        });

    }

    protected function applyEnv() : void
    {
        if ($new = $this->detectAppEnvironmentFromEnv('APP_ENV')) {
            $this->app->setEnvironment($new);
        }
        if (!$this->app->getEnvironment()) {
            $this->app->setEnvironment(Application::PRODUCTION);
        }
    }

    protected function freezeConfig()
    {
        $config = $this->app->getConfig();
        if ($config instanceof Config) {
            $this->app->setConfig(iterator_to_array($config));
        }
    }

    protected function loadEnvFileIfExists(string $envPath)
    {
        if (file_exists($envPath)) {
            (new Env())->load($this->envPath);
        }
    }

    protected function createConfig(array $configFiles, string $envFile='') : Config
    {
        /*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*
         * Read files
         *-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*/
        $config = new Config();
        foreach ($configFiles as $source) {
            if (!is_string($source)) {
                $config->appendSource($source);
                continue;
            }
            $extension = pathinfo($source, PATHINFO_EXTENSION);
            $config->appendSource(static::fileToIterable($source, $extension));
        }

        /*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*
         * Load env (and .env file)
         *-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*/
        $env = new Env();
        if ($envFile && file_exists($envFile)) {
            $env->load($envFile);
        }

        /*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*
         * Add variables
         *-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*/
        $processor = new ConfigVariablesParser();
        $processor->assign('env', $env->__toArray());
        $config->appendPostProcessor($processor);

        return $config;
    }

    protected static function fileToIterable(string $file, string $extension) : iterable
    {
        if ($extension == 'ini') {
            return new IniFileReader($file);
        }
        if ($extension == 'json') {
            return json_decode(file_get_contents($file), true);
        }
        throw new InvalidArgumentException("Config extension '$extension' is not supported");
    }

    protected function detectAppEnvironmentFromEnv(string $envKey)
    {
        if (class_exists(Env::class)) {
            return Env::get($envKey);
        }
        if (array_key_exists($envKey, $_ENV)) {
            return $_ENV[$envKey];
        }
        if (array_key_exists($envKey, $_SERVER)) {
            return $_SERVER[$envKey];
        }
        $value = getenv($envKey);
        return $value !== false ? $value : null;
    }

    protected function createLogger(array $config) : StreamLogger
    {
        if ($this->app->getEnvironment() == Application::TESTING) {
            return new StreamLogger('php://stdout');
        }

        if (isset($config['driver']) && $config['driver'] != 'file') {
            throw new InvalidArgumentException("Unsupported log driver : " . $config['driver']);
        }

        $path = isset($config['path']) && $config['path'] ? $this->absolutePath($config['path']) : 'php://stderr';

        return new StreamLogger($path);
    }
}