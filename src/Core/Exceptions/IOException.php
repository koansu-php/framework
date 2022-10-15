<?php
/**
 *  * Created by mtils on 15.10.2022 at 07:35.
 **/

namespace Koansu\Core\Exceptions;

use JetBrains\PhpStorm\Pure;
use Koansu\Core\Url;
use RuntimeException;
use Throwable;

/**
 * This exception is thrown if a file cannot be opened or a url
 * can not be read...like IOError in python or in java
 */
class IOException extends RuntimeException
{
    /**
     * @var ?Url
     */
    protected $url;

    /**
     * @param Url|string $messageOrUrl
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($messageOrUrl = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->parseMessage($messageOrUrl), $code, $previous);
    }

    public function getUrl() : ?Url
    {
        return $this->url;
    }

    public function setUrl(?Url $url) : IOException
    {
        $this->url = $url;
        return $this;
    }

    protected function parseMessage($messageOrUrl) : string
    {
        if (!$messageOrUrl instanceof Url) {
            return $messageOrUrl;
        }
        $this->url = $messageOrUrl;
        return "The url '$messageOrUrl' cannot be accessed.";
    }
}