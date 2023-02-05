<?php
/**
 *  * Created by mtils on 05.02.2023 at 10:20.
 **/

namespace Koansu\Tests\Fakes;

class GenericEntity
{
    private $new = true;

    public function isNew() : bool
    {
        return $this->new;
    }

    public function makeNew(bool $new=true) : GenericEntity
    {
        $this->new = $new;
        return $this;
    }
}