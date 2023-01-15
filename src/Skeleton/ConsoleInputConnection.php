<?php
/**
 *  * Created by mtils on 03.12.2022 at 12:36.
 **/

namespace Koansu\Skeleton;

use Koansu\Core\AbstractConnection;
use Koansu\Core\Url;
use Koansu\Routing\ArgvInput;
use Koansu\Routing\Contracts\Input;
use Koansu\Skeleton\Contracts\InputConnection;

use function fgets;
use function fopen;
use function strpos;
use function strtolower;

class ConsoleInputConnection extends AbstractConnection implements InputConnection
{
    /**
     * @var string
     */
    protected $uri = 'php://stdin';

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function isInteractive() : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param ?callable $into
     *
     * @return ArgvInput
     */
    public function read(callable $into=null) : Input
    {
        $input = $this->createInput($_SERVER['argv']);
        if ($into) {
            $into($input);
        }
        return $input;
    }

    /**
     * Get something from terminal.
     *
     * @return string
     */
    public function interact(): string
    {
        $handle = fopen($this->uri, 'r');
        $input = trim(fgets($handle));
        fclose($handle);
        return $input;
    }

    /**
     * Return true if the user typed one of the passed values.
     *
     * @param string[] $yes
     *
     * @return bool
     */
    public function confirm(iterable $yes=['y','yes','1','true']) : bool
    {
        $input = $this->interact();
        return in_array(strtolower($input), $yes);
    }

    /**
     * @param Url $url
     *
     * @return resource
     */
    protected function createResource(Url $url)
    {
        return fopen($this->uri, 'r');
    }

    /**
     * @param array $argv
     *
     * @return ArgvInput
     */
    protected function createInput(array $argv) : ArgvInput
    {
        return new ArgvInput($argv, $this->createUrl($argv));
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
}