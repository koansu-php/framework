<?php
/**
 *  * Created by mtils on 03.12.2022 at 08:18.
 **/

namespace Koansu\Core;

use Koansu\Core\Contracts\Connection;

use function fclose;
use function is_resource;

abstract class AbstractConnection implements Connection
{
    /**
     * @var Url
     */
    protected $url;

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var string
     */
    protected $uri = 'php://temp';

    /**
     * AbstractConnection constructor.
     *
     * @param string|Url|null $uri (optional)
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct($uri=null)
    {
        if ($uri) {
            $this->uri = $uri;
        }
        $this->url = new Url($this->uri);
    }

    /**
     * @param Url $url
     *
     * @return resource|object
     * @noinspection PhpMissingReturnTypeInspection
     */
    abstract protected function createResource(Url $url);

    /**
     * {@inheritDoc}
     *
     * @return void
     **/
    public function open() : void
    {
        if (!$this->isOpen()) {
            $this->resource = $this->resource();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     **/
    public function close() : void
    {
        if ($this->isOpen()) {
            fclose($this->resource);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     **/
    public function isOpen() : bool
    {
        return is_resource($this->resource);
    }

    /**
     * {@inheritDoc}
     *
     * @return resource|object|null
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function resource()
    {
        if (!$this->isOpen()) {
            $this->resource = $this->createResource($this->url());
        }
        return $this->resource;
    }

    /**
     * {@inheritDoc}
     *
     * @return Url
     **/
    public function url() : Url
    {
        return $this->url;
    }
}