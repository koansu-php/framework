<?php
/**
 *  * Created by mtils on 28.01.2023 at 10:55.
 **/

namespace Koansu\Filesystem\Skeleton;

use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Filesystem\LocalFilesystem;
use Koansu\Skeleton\AppExtension;

/**
 * Currently this is not worth a class, I prefer to manually bind the filesystem
 * in my app.php
 */
class FilesystemExtension extends AppExtension
{
    protected $singletons = [
        LocalFilesystem::class => Filesystem::class
    ];
}