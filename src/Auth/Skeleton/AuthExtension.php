<?php
/**
 *  * Created by mtils on 20.12.2022 at 22:02.
 **/

namespace Koansu\Auth\Skeleton;

use Koansu\Auth\Auth;
use Koansu\Auth\Routing\IsAllowedMiddleware;
use Koansu\Auth\Routing\IsAuthenticatedMiddleware;
use Koansu\Auth\Routing\SessionAuthMiddleware;
use Koansu\DependencyInjection\Contracts\Container;
use Koansu\Skeleton\AppExtension;
use Koansu\Auth\Contracts\Auth as AuthInterface;
use Koansu\Routing\Contracts\MiddlewareCollection as MiddlewareCollectionContract;
use Koansu\Routing\MiddlewareCollection;

class AuthExtension extends AppExtension
{
    protected $defaultConfig = [
        'nobody' => 'nobody@example.com',
        'system' => 'system@example.com',
    ];

    public function bind() : void
    {
        $this->app->bind(AuthInterface::class, function (Container $app) {
            $auth = $app->create(Auth::class);
            $config = $this->getConfig('auth');
            $auth->setCredentialsForSpecialUser(AuthInterface::GUEST, ['email' => $config['nobody']]);
            $auth->setCredentialsForSpecialUser(AuthInterface::SYSTEM, ['email' => $config['system']]);
            return $auth;
        }, true);
    }

    protected function addMiddleware(MiddlewareCollectionContract $middlewares) : void
    {
        MiddlewareCollection::alias('auth', IsAuthenticatedMiddleware::class);
        MiddlewareCollection::alias('allowed', IsAllowedMiddleware::class);
        $middlewares->add('session-auth', SessionAuthMiddleware::class)->after('session');
    }
}