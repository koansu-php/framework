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
     * @param mixed $attributesOrPayload
     * @param array $envelope
     * @param int $status
     */
    public function __construct($attributesOrPayload = null, array $envelope=[], int $status=0)
    {
        $this->type = Message::TYPE_OUTPUT;
        $this->status = $status;
        if (!$this->contentType) {
            $this->contentType = 'application/octet-stream';
        }

        if (func_num_args() < 2) {
            $attributes = $this->isAssociative($attributesOrPayload) ? $attributesOrPayload : ['payload' => $attributesOrPayload];
            parent::__construct();
            $this->apply($attributes);
            return;
        }

        $attributes = [
            'payload' => $attributesOrPayload,
            'envelope' => $envelope,
            'status'   => $status
        ];

        if ($this->isAssociative($attributesOrPayload)) {
            $attributes['custom'] = $attributesOrPayload;
        }

        parent::__construct($attributes);
        $this->apply($attributes);

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

    protected function apply(array $attributes)
    {
        foreach ($attributes as $key=>$value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    protected function copyStateInto(array &$attributes)
    {
        if (!isset($attributes['status'])) {
            $attributes['status'] = $this->status;
        }
        if (!isset($attributes['statusMessage'])) {
            $attributes['statusMessage'] = $this->statusMessage;
        }
        if (!isset($attributes['contentType'])) {
            $attributes['contentType'] = $this->contentType;
        }
        parent::copyStateInto($attributes);
    }

}