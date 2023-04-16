<?php
/**
 *  * Created by mtils on 14.04.2023 at 10:48.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\Url;
use Koansu\Routing\ConsoleInput;
use Koansu\Skeleton\Contracts\IOAdapter;

use function strpos;

class ConsoleIOAdapter implements IOAdapter
{
    /**
     * @var array
     */
    protected $argv = [];

    public function __construct(array $argv)
    {
        $this->argv = $argv;
    }

    public function read(callable $handler): void
    {
        $this->__invoke($handler);
    }

    public function __invoke(callable $handler): void
    {
        $handler($this->createInput($this->argv), $this->createOutput());
    }

    public function isInteractive(): bool
    {
        return true;
    }

    /**
     * @param array $argv
     *
     * @return ConsoleInput
     */
    protected function createInput(array $argv) : ConsoleInput
    {
        return new ConsoleInput($argv, $this->createUrl($argv));
    }

    /**
     * @param array $argv
     *
     * @return Url
     */
    protected function createUrl(array $argv) : Url
    {
        $command = '';

        foreach ($argv as $i=>$arg) {
            // Skip php filename and options
            if ($i < 1 || strpos($arg, '-') === 0) {
                continue;
            }
            $command = $arg;
            break;
        }

        return new Url("console:$command");
    }

    protected function createOutput() : callable
    {
        return new ConsoleOutput();
    }
}