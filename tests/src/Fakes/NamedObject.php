<?php
/**
 *  * Created by mtils on 28.08.2022 at 20:38.
 **/

namespace Koansu\Tests\Fakes;

class NamedObject
{
    /**
     * @var mixed
     **/
    protected $id;

    /**
     * @var string
     **/
    protected $name = '';

    /**
     * @var string
     **/
    protected $resourceName;

    /**
     * @var int
     **/
    protected $count = 0;

    /**
     * @param mixed  $id   (optional)
     * @param string $name (optional)
     * @param string $resourceName (optional)
     **/
    public function __construct($id = null, string $name = '', string $resourceName = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->resourceName = $resourceName;
    }

    /**
     * @return int|string
     **/
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     **/
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Set the id.
     *
     * @param int|string $id
     *
     * @return self
     **/
    public function setId($id) : NamedObject
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the name.
     *
     * @param string $name
     *
     * @return self
     **/
    public function setName(string $name) : NamedObject
    {
        $this->name = $name;
        return $this;
    }
}