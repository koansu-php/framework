<?php
/**
 *  * Created by mtils on 21.01.2023 at 09:04.
 **/

namespace Koansu\Database\Illuminate;

use Koansu\Database\DatabaseConnectionFactory;
use Koansu\Core\Exceptions\ConfigurationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Events\Dispatcher;

use function class_exists;

class KoansuConnectionFactory implements ConnectionResolverInterface
{
    /**
     * @var string
     */
    protected $defaultConnectionName = 'default';

    /**
     * @var Connection[]
     */
    protected $customConnections = [];

    /**
     * @var Connection[]
     */
    protected $resolvedConnections = [];

    /**
     * @var DatabaseConnectionFactory
     */
    private $koansuFactory;

    /**
     * @var ConnectionFactory
     */
    private $nativeFactory;

    /**
     * @var DispatcherContract
     */
    private $events;

    public function __construct(DatabaseConnectionFactory $factory=null)
    {
        $this->koansuFactory = $factory;
    }

    /**
     * {@inheritDoc}
     *
     * @param string|null $name
     *
     * @return Connection
     */
    public function connection($name = null) : Connection
    {
        $name = $name ?: $this->defaultConnectionName;

        if (isset($this->customConnections[$name])) {
            return $this->customConnections[$name];
        }
        if (!isset($this->resolvedConnections[$name])) {
            $this->resolvedConnections[$name] = $this->createFromKoansuFactory($name);
        }
        return $this->resolvedConnections[$name];
    }

    /**
     * @return string
     */
    public function getDefaultConnection() : string
    {
        return $this->defaultConnectionName;
    }

    /**
     * @param string $name
     */
    public function setDefaultConnection($name)
    {
        $this->defaultConnectionName = $name;
    }

    /**
     * Get a custom connection for $name or null if none was set.
     *
     * @param string $name
     * @return Connection|null
     */
    public function getCustomConnection(string $name) : ?Connection
    {
        return $this->customConnections[$name] ?? null;
    }

    /**
     * Set a custom connection for $name.
     *
     * @param string $name
     * @param Connection|null $connection
     */
    public function setCustomConnection(string $name, ?Connection $connection)
    {
        $this->customConnections[$name] = $connection;
    }

    /**
     * @return ?DatabaseConnectionFactory
     */
    public function getKoansuFactory(): ?DatabaseConnectionFactory
    {
        return $this->koansuFactory;
    }

    /**
     * @param DatabaseConnectionFactory|null $koansuFactory
     */
    public function setKoansuFactory(?DatabaseConnectionFactory $koansuFactory): void
    {
        $this->koansuFactory = $koansuFactory;
    }

    /**
     * @return DispatcherContract|null
     */
    public function getEvents(): ?DispatcherContract
    {
        if ($this->events) {
            return $this->events;
        }
        // If Illuminate\Events is installed
        if (class_exists(Dispatcher::class)) {
            $this->events = new Dispatcher();
        }

        $this->events = new DetachedDispatcher();
        return $this->events;
    }

    /**
     * @param DispatcherContract|null $events
     * @return KoansuConnectionFactory
     */
    public function setEvents(?DispatcherContract $events=null): KoansuConnectionFactory
    {
        $this->events = $events;
        return $this;
    }

    /**
     * Convert an koansu database configuration into laravel.
     *
     * @param array $koansuConfig
     *
     * @return array
     */
    public static function configToLaravelConfig(array $koansuConfig) : array
    {
        $map = [
            'user' => 'username'
        ];
        $laravelConfig = [];
        foreach ($koansuConfig as $key=> $value) {
            $laravelConfig[$map[$key] ?? $key] = $value;
        }
        return $laravelConfig;
    }

    /**
     * Create illuminate connection from an koansu' connection from ConnectionPool.
     *
     * @param string $name
     * @return Connection
     *
     * @throws ConfigurationException
     */
    protected function createFromKoansuFactory(string $name) : Connection
    {
        if (!$this->koansuFactory) {
            throw new ConfigurationException("You have to assign a ConnectionPool to create connections from it");
        }
        $koansuConnection = $this->koansuFactory->connection($name);
        $config = static::configToLaravelConfig($this->koansuFactory->urlToConfig($koansuConnection->url()));
        $config['pdo'] = function () use ($koansuConnection) {
            if (!$koansuConnection->isOpen()) {
                $koansuConnection->open();
            }
            return $koansuConnection->resource();
        };
        $connection = $this->getNativeFactory()->make($config, $name);

        if ($events = $this->getEvents()) {
            $connection->setEventDispatcher($events);
        }
        return $connection;
    }

    /**
     * @return ConnectionFactory
     */
    protected function getNativeFactory() : ConnectionFactory
    {
        if (!$this->nativeFactory) {
            $this->nativeFactory = new SharedPDOConnectionFactory(new Container());
        }
        return $this->nativeFactory;
    }
}