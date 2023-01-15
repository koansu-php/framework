<?php
/**
 *  * Created by mtils on 08.01.2023 at 13:05.
 **/

namespace Koansu\Database;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\ExtendableTrait;
use Koansu\Core\Type;
use Koansu\Core\Url;
use Koansu\Database\Contracts\DatabaseConnection;
use TypeError;

class DatabaseConnectionFactory implements Extendable
{
    use ExtendableTrait;

    protected $connections = [];

    /**
     * @var array[]
     */
    protected $configurations = [];

    /**
     * @var string
     */
    protected $defaultConnectionName = 'default';

    public function connection($nameOrUrl=null) : DatabaseConnection
    {
        $nameOrUrl = $nameOrUrl ?: $this->getDefaultConnectionName();
        $key = (string)$nameOrUrl;

        if (isset($this->connections[$key])) {
            return $this->connections[$key];
        }

        $url = $nameOrUrl instanceof Url ? $nameOrUrl : new Url($nameOrUrl);

        $connection = $this->callUntilNotNull(
            $this->allExtensions(),
            [$url, $this->configurations[$key] ?? [], $this],
            true
        );

        if (!$connection instanceof DatabaseConnection) {
            throw new TypeError("The handler for $url did not return a DatabaseConnection, but " . Type::of($connection));
        }

        $this->connections[$key] = $connection;
        $this->connections[(string)$connection->url()];

        return $connection;
    }

    /**
     * @return array[]
     */
    public function getConfigurations(): array
    {
        return $this->configurations;
    }

    /**
     * @param array[] $configurations
     */
    public function setConfigurations(array $configurations): void
    {
        $this->configurations = $configurations;
    }

    public function configure(array $configurations, string $defaultConnectionName='')
    {
        $this->configurations = $configurations;
        if ($defaultConnectionName) {
            $this->setDefaultConnectionName($defaultConnectionName);
        }
    }

    public function urlToConfig(Url $url) : array
    {
        if($url->scheme == 'sqlite') {
            return [
                'driver'    => 'sqlite',
                'database'  => $url->host == 'memory' ? ':memory:' : (string)$url->path
            ];
        }
        $config = [
            'driver'    => $url->scheme,
            'host'      => $url->host,
            'database'  => $url->path->first(),
            'user'      => $url->user,
            'password'  => $url->password
        ];
        if ($url->port) {
            $config['port'] = $url->port;
        }
        foreach ($url->query as $key=>$value) {
            $config[$key] = $value;
        }

        return $config;
    }

    /**
     * @return string
     */
    public function getDefaultConnectionName(): string
    {
        return $this->defaultConnectionName;
    }

    /**
     * @param string $defaultConnectionName
     */
    public function setDefaultConnectionName(string $defaultConnectionName): void
    {
        $this->defaultConnectionName = $defaultConnectionName;
    }

}