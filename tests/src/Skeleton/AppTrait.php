<?php
/**
 *  * Created by mtils on 28.01.2023 at 09:45.
 **/

namespace Koansu\Tests\Skeleton;

use Koansu\DependencyInjection\Contracts\Container;
use Koansu\Skeleton\Application;

use Koansu\Skeleton\SkeletonExtension;

use function get_class;
use function realpath;
use function var_dump;

/**
 * @property string[] extensions Add this to add your AppExtension classes
 */
trait AppTrait
{
    /**
     * @var Application
     **/
    protected $_app;

    /**
     * @param ?string $binding    (optional)
     * @param array  $parameters (optional)
     *
     * @return Application|object
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function app(string $binding = null, array $parameters = [])
    {
        $app = $this->appInstance();

        if (!$app->wasInitialized()) {
            $this->initApplication($app);
            $app->on(Application::STEP_CONFIGURE, function ($app) {
                $this->configureApplication($app);
            });
            $app->on(Application::STEP_BOOT, function ($app) {
                $this->boot($app);
            });
            // Manually trigger boot because we are not handling input always
            $app->boot();
        }
        return $binding ? $app->__invoke($binding, $parameters) : $app;
    }

    /**
     * @return Application
     */
    protected function appInstance() : Application
    {
        if (!$this->_app) {
            $this->_app = $this->createApplication(realpath(__DIR__.'/../../../'));
        }
        return $this->_app;
    }

    /**
     * Create the application and return it.
     *
     * @param string $appPath
     *
     * @return Application
     **/
    protected function createApplication(string $appPath) : Application
    {
        $app = new Application($appPath);

        $app->setVersion('0.1.9.4')
            ->setName('Integration Test Application');

        return $app;
    }

    /**
     * Overwrite this method to configure the application before booting.
     *
     * @param Application $app
     */
    protected function configureApplication(Application $app) : void
    {
        //
    }

    /**
     * Boot add the bootstrappers and boot the application.
     *
     * @param Application $app
     **/
    protected function initApplication(Application $app) : void
    {
        $this->addExtensions($app);
    }

    /**
     * Overwrite this method for simple boot configurations
     *
     * @param Application $app
     * @return void
     */
    protected function boot(Application $app) : void
    {
        //
    }

    /**
     * Add the bootstrappers to the bootmanager.
     *
     * @param Application $app
     */
    protected function addExtensions(Application $app) : void
    {
        foreach ($this->extensions() as $extensionClass) {
            $ext = new $extensionClass($app);
        }
    }

    /**
     * Return all the bootstrappers this test needs. Defaults to all.
     * Assign an array of class names named $this->bootstrappers to
     * change the bootstrappers.
     *
     * @return string[]
     **/
    protected function extensions() : array
    {
        if (isset($this->extensions)) {
            return $this->extensions;
        }

        $extensions = [
            SkeletonExtension::class
        ];

        if (!isset($this->extraExtensions)) {
            return $extensions;
        }

        foreach (($this->extraExtensions) as $extension) {
            $extensions[] = $extension;
        }

        return $extensions;
    }
}