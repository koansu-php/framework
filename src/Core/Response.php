<?php
/**
 *  * Created by mtils on 26.10.2022 at 11:04.
 **/

namespace Koansu\Core;

use Koansu\Core\ImmutableMessage;
use Koansu\Core\Message;
use Koansu\Core\Type;

use function func_num_args;
use function property_exists;

/**
 * This is a default response. It is an ImmutableMessage that is
 * returned by the application fulfilling a request.
 *
 * @property-read int status
 * @property-read string statusMessage
 * @property-read string contentType
 */
class Response extends ImmutableMessage
{

    /**
     * @var int
     */
    protected $status = 0;

    /**
     * @var string
     */
    protected $statusMessage = '';

    /**
     * @var string
     */
    protected $contentType = '';

    /**
     * Create a new response.
     * Pass only one assoziative array parameter to fill all the properties (like in message)
     * Pass more than one parameter to set payload, envelope and status.
     * If $data is not an associative array it will never be taken as attributes.
     *
     * @param mixed $payload (optional)
     * @param array $envelope (optional)
     * @param int $status (optional)
     * @param string $contentType (optional)
     */
    public function __construct($payload = null, array $envelope=[], int $status=0, string $contentType='application/octet-stream')
    {
        parent::__construct($payload, $envelope, Message::TYPE_OUTPUT);
        $this->status = $status;
        $this->contentType = $contentType;
    }

    public function withStatus($code, $reasonPhrase='')
    {
        $message = func_num_args() == 2 ? $reasonPhrase : $this->statusMessage;
        return $this->replicate(['status' => $code, 'statusMessage' => $message]);
    }

    public function withContentType(string $contentType)
    {
        return $this->replicate(['contentType' => $contentType]);
    }

    public function __get(string $key)
    {
        if ($key == 'status') {
            return $this->status;
        }
        if ($key == 'statusMessage') {
            return $this->statusMessage;
        }
        if ($key == 'contentType') {
            return $this->contentType;
        }
        return parent::__get($key);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (Type::isStringable($this->payload)) {
            return (string)$this->payload;
        }
        return '';
    }

}