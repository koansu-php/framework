<?php
/**
 *  * Created by mtils on 21.12.2022 at 21:12.
 **/

namespace Koansu\Schema\Illuminate;

use Closure;
use Koansu\Core\Contracts\HasMethodHooks;
use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Core\Type;
use Koansu\Schema\Exceptions\MigrationClassNotFoundException;
use Koansu\Schema\Contracts\MigrationRunner;
use Koansu\DependencyInjection\Lambda;
use Koansu\Core\HookableTrait;
use Koansu\Core\CustomFactoryTrait;

class IlluminateMigrationRunner
{

}