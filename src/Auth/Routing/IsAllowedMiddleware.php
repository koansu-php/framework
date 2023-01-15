<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:55.
 **/

namespace Koansu\Auth\Routing;

use Koansu\Auth\Contracts\Auth;
use Koansu\Auth\Exceptions\LoggedOutException;
use Koansu\Auth\Exceptions\NotAllowedException;
use Koansu\Routing\Contracts\Input;

class IsAllowedMiddleware
{
    /**
     * @var Auth
     */
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function __invoke(Input $input, callable $next, string $resource, string $operation='')
    {
        if (!$user = $input->getUser() ) {
            throw new LoggedOutException('No user was set at the request. This is an error.');
        }
        $operation = $operation ?: Auth::ACCESS;
        if (!$this->auth->allowed($user, $resource, $operation)) {
            throw new NotAllowedException("The current user is now allowed to access $resource:$operation");
        }
        return $next($input);
    }
}