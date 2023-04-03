<?php
/**
 *  * Created by mtils on 26.10.2022 at 18:21.
 **/

namespace Koansu\Routing;

use InvalidArgumentException;
use Koansu\Core\Message;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;

use function is_array;

class GenericInput extends Message implements Input
{
    use InputTrait;

    /**
     * @var string
     */
    protected $method = '';

    public function __construct($payload=[], array $envelope=[], string $transport=Message::TRANSPORT_APP)
    {
        parent::__construct($payload, $envelope, Message::TYPE_INPUT, $transport);
        if (!$this->clientType) {
            $this->clientType = Input::CLIENT_WEB;
        }
    }

    public function getFrom(string $from, $parameter = '')
    {
        if ($from != Message::POOL_CUSTOM) {
            throw new InvalidArgumentException("GenericInput just has custom input, no from $from");
        }
        if (is_array($parameter)) {
            return $this->collectFrom($from, $parameter);
        }
        return $parameter ? $this->custom[$parameter] ?? null : $this->custom;
    }

    public function setDeterminedContentType(string $contentType) : GenericInput
    {
        $this->determinedContentType = $contentType;
        return $this;
    }

    /**
     * @param string|int $offset
     * @param mixed $value
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->custom[$offset] = $value;
    }

    /**
     * @param string|int $offset
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->custom[$offset]);
    }

    public function setMethod(string $method): GenericInput
    {
        $this->method = $method;
        return $this;
    }

    public function setUrl(Url $url): GenericInput
    {
        $this->url = $url;
        return $this;
    }

    public function setRouteScope($scope): GenericInput
    {
        $this->applyRouteScope($scope);
        return $this;
    }

    /**
     * @param string $clientType
     * @return $this
     */
    public function setClientType(string $clientType): Input
    {
        $this->clientType = $clientType;
        return $this;
    }

    public function makeRouted(Route $route, callable $handler, array $parameters = []): Input
    {
        $this->matchedRoute = $route;
        $this->handler = $handler;
        $this->routeParameters = $parameters;
        return $this;
    }

    /**
     * Shortcut to create an input object with the passed client type.
     *
     * @param string                 $clientType
     * @param string|RouteScope|null $scope
     *
     * @return Input|GenericInput
     */
    public static function clientType(string $clientType, $scope=null)
    {
        $input = (new static())->setClientType($clientType);
        return $scope === null ? $input : $input->setRouteScope($scope);
    }

    /**
     * Shortcut to create an input object just for the passed scope.
     *
     * @param string|RouteScope     $scope
     * @param string|null           $clientType
     *
     * @return Input|GenericInput
     */
    public static function scope($scope, string $clientType=null)
    {
        $input = (new static())->setRouteScope($scope);
        return $clientType === null ? $input : $input->setClientType($clientType);
    }
}