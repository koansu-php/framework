<?php
/**
 *  * Created by mtils on 24.12.2022 at 07:39.
 **/

namespace Koansu\SQL;

use Koansu\Core\Str;

class Column extends Str
{
    /**
     * @var string
     */
    protected $key = '';

    /**
     * @var string
     */
    protected $alias = '';

    /**
     * @param string $key   (optional)
     * @param string $alias (optional)
     **/
    public function __construct(string $key='', string $alias='')
    {
        parent::__construct($key, 'application/x-key');
        $this->alias = $alias;
    }

    public function alias() : string
    {
        return $this->alias;
    }
}