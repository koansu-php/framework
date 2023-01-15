<?php
/**
 *  * Created by mtils on 21.12.2022 at 20:56.
 **/

namespace Koansu\Schema\Contracts;

interface MigrationRunner
{
    public function upgrade(string $file,   bool $simulate=false);
    public function downgrade(string $file, bool $simulate=false);
}