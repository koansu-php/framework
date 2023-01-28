<?php
/**
 *  * Created by mtils on 21.12.2022 at 21:17.
 **/

namespace Koansu\Schema\Exceptions;

class MigratorInstallationException extends MigratorException
{
    const NOT_INSTALLED   = 4040;
    const NOT_INSTALLABLE = 5000;
}