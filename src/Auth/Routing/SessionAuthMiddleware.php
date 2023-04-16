<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:59.
 **/

namespace Koansu\Auth\Routing;

use Koansu\Auth\Contracts\Auth;
use Koansu\Routing\ConsoleInput;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Routing\Session;
use Throwable;

class SessionAuthMiddleware
{
    /**
     * @var Auth
     */
    protected $auth;

    protected $sessionKey = 'koansu-auth-credentials';

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function __invoke(Input $input, callable $next)
    {
        if ($input instanceof ConsoleInput) {
            return $next($this->tryToAssignSystemUser($input));
        }
        if (!$input instanceof HttpInput || !isset($input->session[$this->sessionKey])) {
            return $next($input->withUser($this->auth->specialUser(Auth::GUEST)));
        }
        $user = $this->auth->userByCredentials($input->session[$this->sessionKey]);
        return $user ? $next($input->withUser($user)) : $next($input);
    }

    protected function tryToAssignSystemUser(Input $input) : Input
    {
        try {
            return $input->withUser($this->auth->specialUser(Auth::SYSTEM));
        } catch (Throwable $e) {
            return $input;
        }
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