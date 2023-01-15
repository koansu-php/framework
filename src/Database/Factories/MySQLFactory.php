<?php
/**
 *  * Created by mtils on 09.01.2023 at 20:46.
 **/

namespace Koansu\Database\Factories;

use Koansu\Core\Url;
use Koansu\Database\PDOConnection;
use TypeError;

use function implode;
use function is_array;
use function trim;

class MySQLFactory
{
    public function __invoke($config) : ?PDOConnection
    {
        if ($config instanceof Url) {
            return $this->createByUrl($config);
        }
        if (!is_array($config)) {
            throw new TypeError("Config must be array or instanceof " . Url::class);
        }
        if (!isset($config['driver']) || $config['driver'] != 'mysql') {
            return null;
        }
        return $this->__invoke($this->configToUrl($config));
    }

    public static function configToUrl(array $config, string $databasePath='') : Url
    {
        return (new Url())
            ->scheme($config['driver'])
            ->host($config['host'])
            ->path($config['database'])
            ->user($config['user'])
            ->password($config['password']);
    }

    protected function createByUrl(Url $url) : ?PDOConnection
    {
        if ($url->scheme != 'mysql') {
            return null;
        }
        return new PDOConnection($url, $this->urlToDsn($url));
    }

    protected function urlToDsn(Url $url) : string
    {

        $driver = $url->scheme;
        $db = $url->path ?: '';
        $port = $url->port;
        $host = $url->host;

        $parts = [];

        if ($db) {
            $parts[] = "dbname=" . trim($db, '/');
        }

        if ($host) {
            $parts[] = "host=$host";
        }

        if ($port) {
            $parts[] = "port=$port";
        }

        return "$driver:" . implode(';', $parts);

    }
}