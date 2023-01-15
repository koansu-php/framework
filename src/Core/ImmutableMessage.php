<?php
/**
 *  * Created by mtils on 12.12.2021 at 08:22.
 **/

namespace Koansu\Core;

use Koansu\Routing\Contracts\Input;

use function array_key_exists;
use function func_get_args;
use function is_array;

/**
 * This is the immutable version of message,
 *
 * You cannot overwrite values in this object, only accepted or ignored by accept().
 *
 * The classes have next and previous references to the cloned versions so you
 * can get the overwritten data be the next clone.
 *
 * @property-read ImmutableMessage|null previous
 * @property-read ImmutableMessage|null next
 */
class ImmutableMessage extends Message
{
    /**
     * @var ImmutableMessage|null
     */
    protected $previous;

    /**
     * @var ImmutableMessage|null
     */
    protected $next;

    /**
     * Return a new instance that has $key set to $value. Pass an array for
     * multiple changed parameters.
     *
     * @param string|array $key
     * @param mixed $value (optional)
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function with($key, $value=null)
    {
        $attributes = is_array($key) ? $key : [$key => $value];
        $custom = $this->custom;
        foreach ($attributes as $key=>$value) {
            $custom[$key] = $value;
        }
        return $this->replicate(['custom' => $custom]);
    }

    /**
     * Return a new instance without data for $key(s)
     *
     * @param string|array $key
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function without($key)
    {
        $keys = is_array($key) ? $key : func_get_args();
        $custom = $this->custom;
        foreach ($keys as $key) {
            unset($custom[$key]);
        }
        return $this->replicate(['custom' => $custom]);
    }

    /**
     * Clone and change type.
     *
     * @param string $type
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withType(string $type)
    {
        return $this->replicate(['type' => $type]);
    }

    /**
     * Clone and change transport.
     *
     * @param string $transport
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withTransport(string $transport)
    {
        return $this->replicate(['transport' => $transport]);
    }

    /**
     * Clone and change envelope.
     *
     * @param array $envelope
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withEnvelope(array $envelope)
    {
        return $this->replicate(['envelope' => $envelope]);
    }

    /**
     * Clone and change payload.
     *
     * @param $payload
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withPayload($payload)
    {
        return $this->replicate(['payload' => $payload]);
    }

    /**
     * @param array $custom
     * @return $this|ImmutableMessage
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withCustom(array $custom)
    {
        return $this->replicate(['custom' => $custom]);
    }

    public function __get(string $key)
    {
        switch ($key) {
            case 'previous':
                return $this->previous;
            case 'next':
                return $this->next;
        }
        return parent::__get($key);
    }

    /**
     * Try to get the last created input (by middleware)
     *
     * @param Input $input
     * @return Input
     */
    public static function lastByNext(Input $input) : Input
    {
        if ($input instanceof ImmutableMessage && $input->next instanceof Input) {
            return self::lastByNext($input->next);
        }
        return $input;
    }

    /**
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function replicate(array $properties=[])
    {
        $copy = clone $this;
        $copy->previous = $this;
        $copy->next = null;
        $this->next = $copy;
        if (!$properties) {
            return $copy;
        }
        foreach ($properties as $property=>$value) {
            $copy->$property = $value;
        }
        return $copy;
    }

    protected function isAssociative($data) : bool
    {
        if (!is_array($data)) {
            return false;
        }
        foreach ($data as $key=>$value) {
            return $key !== 0;
        }
        return true;
    }
}
