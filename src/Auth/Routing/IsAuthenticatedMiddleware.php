<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:57.
 **/

namespace Koansu\Auth\Routing;

use Koansu\Auth\Contracts\Auth;
use Koansu\Auth\Exceptions\LoggedOutException;
use Koansu\Routing\Contracts\Input;

class IsAuthenticatedMiddleware
{
    /**
     * @var Auth
     */
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function __invoke(Input $input, callable $next)
    {
        if (!$user = $input->getUser() ) {
            throw new LoggedOutException('No user was set at the request. This is an error.');
        }
        if (!$this->auth->isAuthenticated($user)) {
            throw new LoggedOutException('Nobody is logged in.');
        }
        return $next($input);
    }
}