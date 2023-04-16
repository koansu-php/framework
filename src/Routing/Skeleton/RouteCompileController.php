<?php
/**
 *  * Created by mtils on 18.12.2022 at 10:03.
 **/

namespace Koansu\Routing\Skeleton;

use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Storage;
use Koansu\Core\Type;
use Koansu\Routing\Contracts\Router;
use Koansu\Routing\Contracts\RouteRegistry;
use Koansu\Routing\CompilableRouter;
use Koansu\Skeleton\ConsoleOutput;

use TypeError;

use function method_exists;

class RouteCompileController
{
    /**
     * @var Storage|Arrayable
     */
    private $storage;

    public function compile(RouteRegistry $registry, Router $router, ConsoleOutput $out) : int
    {
        $storageClass = Type::short($this->storage);
        $message = "Compiling routes into cache stored by <comment>$storageClass</comment>";
        if ($target = $this->getTarget()) {
            $message .= " at <comment>$target</comment>...";
        }
        $out->line($message);

        $compiledData = $registry->compile($router);
        $this->storage->clear();

        foreach ($compiledData as $key=>$value) {
            $this->storage->offsetSet($key, $value);
        }

        if ($this->storage->isBuffered()) {
            $this->storage->persist();
            $out->line('<mute>Storage is buffered.</mute> <info>Manually persisted storage. Successfully finished.</info>');
            return 0;
        }

        $out->line('<mute>Storage is unbuffered.</mute> <info>Trusting automatic save mechanism. Successfully finished.</info>');
        return 0;
    }

    /**
     * Show some status information about the cache.
     *
     * @param ConsoleOutput $out
     * @return int
     */
    public function status(ConsoleOutput $out) : int
    {
        $storageClass = Type::short($this->storage);
        $message = "Checking route cache stored by <comment>$storageClass</comment>";
        if ($target = $this->getTarget()) {
            $message .= " at <comment>$target</comment>...";
        }

        $out->line($message);

        $compiledData = $this->storage->__toArray();
        if ($this->hasRoutingData($compiledData)) {
            $out->line('<info>Routes are cached</info>');
            return 0;
        }
        $out->line('<comment>Routes are not cached</comment>');

        return 0;
    }

    public function clear(ConsoleOutput $out) : int
    {
        $storageClass = Type::short($this->storage);
        $message = "Delete route cache stored by $storageClass";
        if ($target = $this->getTarget()) {
            $message .= " at $target...";
        }
        $out->line($message);
        $data = $this->storage->__toArray();
        if (!$this->hasRoutingData($data)) {
            $out->line('<comment>Routes cache was empty or did not exist. No need to clear it. Aborted</comment>');
            return 0;
        }

        $this->storage->clear();
        if ($this->storage->isBuffered()) {
            $this->storage->persist();
        }
        $out->line('<info>Routes cache was cleared.</info>');
        return 0;
    }

    /**
     * @return Storage|Arrayable
     * @noinspection PhpDocSignatureInspection
     */
    public function getStorage(): Storage
    {
        return $this->storage;
    }

    /**
     * @param Storage|Arrayable $storage
     * @noinspection PhpDocSignatureInspection
     */
    public function setStorage(Storage $storage): void
    {
        if (!$storage instanceof Arrayable) {
            throw new TypeError('The passed storage has to be arrayable');
        }
        $this->storage = $storage;
    }

    protected function hasRoutingData(array $compiledData) : bool
    {
        return isset($compiledData[CompilableRouter::KEY_VALID]) && $compiledData[CompilableRouter::KEY_VALID];
    }

    protected function getTarget() : string
    {
        if (method_exists($this->storage, 'getUrl')) {
            return $this->storage->getUrl();
        }
        return '';
    }
}