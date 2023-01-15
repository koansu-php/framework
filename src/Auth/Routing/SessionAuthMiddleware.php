<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:59.
 **/

namespace Koansu\Auth\Routing;

use Koansu\Auth\Contracts\Auth;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\Session;
use Koansu\Routing\ArgvInput;
use Koansu\Routing\HttpInput;
use Throwable;

class SessionAuthMiddleware
{
    /**
     * @var Auth
     */
    protected $auth;

    protected $sessionKey = 'ems-auth-credentials';

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function __invoke(Input $input, callable $next)
    {
        if ($input instanceof ArgvInput) {
            try {
                $user = $this->auth->specialUser(Auth::SYSTEM);
                return $next($input->withUser($user));
            } catch (Throwable $e) {
                return $next($input);
            }
        }
        if (!$input instanceof HttpInput || !isset($input->session[$this->sessionKey])) {
            return $next($input->withUser($this->auth->specialUser(Auth::GUEST)));
        }
        $user = $this->auth->userByCredentials($input->session[$this->sessionKey]);
        return $next($input->withUser($user));
    }

    /**
     * @return string
     */
    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }

    /**
     * @param string $sessionKey
     */
    public function setSessionKey(string $sessionKey): void
    {
        $this->sessionKey = $sessionKey;
    }

    public function persistInSession(array $credentials, Session $session) : void
    {
        $session[$this->sessionKey] = $credentials;
    }

    public function removeFromSession(Session $session) : bool
    {
        if (isset($session[$this->sessionKey])) {
            unset($session[$this->sessionKey]);
            return true;
        }
        return false;
    }
}