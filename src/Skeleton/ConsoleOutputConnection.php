<?php
/**
 *  * Created by mtils on 03.12.2022 at 14:15.
 **/

namespace Koansu\Skeleton;

use Koansu\Console\AnsiRenderer;
use Koansu\Core\AbstractConnection;
use Koansu\Core\Response;
use Koansu\Core\Url;
use Koansu\Skeleton\Contracts\OutputConnection;

use function fwrite;
use function is_bool;

use const PHP_EOL;

class ConsoleOutputConnection extends AbstractConnection implements OutputConnection
{

    /**
     * @var string
     */
    protected $uri = 'php://stdout';

    /**
     * @var AnsiRenderer
     */
    private $renderer;

    /**
     * @var bool
     */
    private $formattedOutput = true;

    public function __construct(...$args)
    {
        $this->renderer = new AnsiRenderer();
        parent::__construct(...$args);
    }


    /**
     * Output a line. Replace any tags with console color styles.
     *
     * @param string $output
     * @param bool   $formatted (optional)
     * @param string $newLine (default: PHP_EOL)
     */
    public function line(string $output, bool $formatted=null, string $newLine=PHP_EOL)
    {
        $formatted = is_bool($formatted) ? $formatted : $this->shouldFormatOutput();
        $output = $formatted ? $this->renderer->format($output) : $this->renderer->plain($output);
        $this->write($output . $newLine);
    }

    /**
     * Returns true when the tags should be colored. (Otherwise they get removed)
     *
     * @return bool
     */
    public function shouldFormatOutput() : bool
    {
        return $this->formattedOutput;
    }

    /**
     * @param $output
     * @param bool $lock
     * @return bool
     */
    public function write($output, bool $lock = false) : bool
    {
        if (!$output instanceof Response) {
            return (bool)fwrite($this->resource(), $output);
        }
        $payload = $output->payload;
        $stringPayload = "$payload";

        if ($output->contentType != AnsiRenderer::LINE_CONTENT_TYPE) {
            return (bool)fwrite($this->resource(), $stringPayload);
        }
        $lines = explode(PHP_EOL, $stringPayload);

        foreach ($lines as $line) {
            $this->line($line);
        }
        return true;
    }

    /**
     * @param Url $url
     * @return false|object|resource
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function createResource(Url $url)
    {
        return fopen($this->uri, 'w');
    }

}