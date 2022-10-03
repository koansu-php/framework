<?php /** @noinspection ALL */

/**
 *  * Created by mtils on 07.11.2021 at 06:34.
 **/

use Koansu\Core\Contracts\Hookable;

$test = new class() {};

class AfterAnonymous {

    protected $test = '1';

    public function run(Hookable $hookable) : bool
    {
        return false;
    }
};