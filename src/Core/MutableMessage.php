<?php
/**
 *  * Created by mtils on 24.08.19 at 07:37.
 **/

namespace Koansu\Core;

use Koansu\Core\Exceptions\SymbolNotFoundException;

use function is_array;
use function is_bool;

/**
 * Class Message
 *
 * This is the mutable version of AbstractMessage.
 *
 * @property string type The type of message in/out/log/custom
 * @property bool   accepted
 * @property bool   ignored
 * @property string transport The transport media to send the message
 * @property array custom The manually set attributes
 * @property array envelope The metadata of this message like http headers
 * @property mixed payload The raw payload
 */
class MutableMessage extends Message
{

    /**
     * This method allows setting values.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $key, $value)
    {
        switch ($key) {
            case 'type':
                $this->type = $value;
                return;
            case 'accepted':
                $this->setAccepted($value);
                return;
            case 'ignored':
                $this->setAccepted(is_bool($value) ? !$value : $value);
                return;
            case 'transport':
                $this->transport = $value;
                return;
            case 'custom':
                $this->custom = $value;
                return;
            case 'envelope':
                $this->envelope = $value;
                return;
            case 'payload':
                $this->payload = $value;
                return;
        }
        throw new SymbolNotFoundException($key, SymbolNotFoundException::PROPERTY_NOT_FOUND);
    }

    /**
     * Fluently set values.
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function set($key, $value=null) : MutableMessage
    {
        $values = is_array($key) ? $key : [$key=>$value];
        foreach ($values as $key=>$value) {
            $this->offsetSet($key, $value);
        }
        return $this;
    }

    /**
     * @param string|int $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->custom[$offset] = $value;
    }

    /**
     * @param string|int $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->custom[$offset]);
    }

}