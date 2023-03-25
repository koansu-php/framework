<?php
/**
 *  * Created by mtils on 26.02.2023 at 18:56.
 **/

namespace Koansu\Routing\Middleware;

use Koansu\Core\Contracts\Serializer;
use Koansu\Core\Response;
use Koansu\Http\Cookie;
use Koansu\Http\HttpResponse;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Routing\Session;
use SessionHandler;
use SessionHandlerInterface;
use UnexpectedValueException;

use function get_class;
use function is_array;
use function session_create_id;
use function session_get_cookie_params;
use function session_name;
use function session_save_path;

class SessionGuard
{
    public const COOKIE_NAME = 'name';

    /**
     * @var SessionHandlerInterface
     */
    protected $handler;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var callable
     */
    protected $idGenerator;

    /**
     * @var int
     */
    protected $lifeTime = 120;

    /**
     * @var array
     */
    protected $cookieConfig = [];

    public function __construct(SessionHandlerInterface $handler, Serializer $serializer)
    {
        $this->handler = $handler;
        $this->serializer = $serializer;
    }

    public function __invoke(Input $input, callable $next) : Response
    {
        if (!$this->shouldHaveSession($input)) {
            return $next($input);
        }

        /** @var HttpInput $input */
        $id = $this->getOrCreateSessionId($input);

        $session = $this->getSession($id);

        $newRequest = $input->withSession($session);

        $response = $next($newRequest);

        if (!$response instanceof HttpResponse) {
            throw new UnexpectedValueException('The response to HttpInput should be HttpResponse not ' . get_class($response));
        }

        $sessionData = $newRequest->session ? $newRequest->session->__toArray() : [];

        if (!$sessionData) {
            $this->handler->destroy($id);
            return $response;
        }

        $idHasChanged = $newRequest->session->getId() != $id;

        if (!$this->hasCookie($input, $this->getCookieName()) || $idHasChanged) {
            $response = $response->withCookie(
                $this->createCookie($this->getCookieConfig(), $newRequest->session->getId(), $input)
            );
        }

        return $response;
    }

    public function generateId() : string
    {
        return $this->getIdGenerator()();
    }

    /**
     * @return string
     */
    public function getCookieName() : string
    {
        if (isset($this->cookieConfig[self::COOKIE_NAME]) && $this->cookieConfig[self::COOKIE_NAME]) {
            return $this->cookieConfig[self::COOKIE_NAME];
        }
        return session_name();
    }

    public function write(Session $session) : bool
    {
        $data = $session->__toArray();
        if (!$data) {
            return $this->handler->destroy($session->getId());
        }
        return $this->handler->write(
            $session->getId(),
            $this->serializer->serialize($data)
        );
    }

    /**
     * @return int
     */
    public function getLifeTime() : int
    {
        return $this->lifeTime;
    }

    /**
     * @param int $lifeTime
     * @return SessionGuard
     */
    public function setLifeTime(int $lifeTime) : SessionGuard
    {
        $this->lifeTime = $lifeTime;
        return $this;
    }

    /**
     * @return array
     */
    public function getCookieConfig(): array
    {
        if ($this->cookieConfig) {
            return $this->cookieConfig;
        }
        $this->cookieConfig = session_get_cookie_params();
        $this->cookieConfig[self::COOKIE_NAME] = session_name();
        return $this->cookieConfig;
    }

    /**
     * @param array $cookieConfig
     * @return SessionGuard
     */
    public function setCookieConfig(array $cookieConfig): SessionGuard
    {
        $config = $this->getCookieConfig();
        foreach ($cookieConfig as $key=>$value) {
            $config[$key] = $value;
        }
        $this->cookieConfig = $config;
        return $this;
    }

    public function getSession(string $id) : Session
    {

        if ($this->handler instanceof SessionHandler) {
            $this->handler->open(session_save_path(), session_name());
        }
        if (!$raw = $this->handler->read($id)) {
            return new Session([], $id);
        }
        $data = $this->serializer->deserialize($raw);
        if (!is_array($data)) {
            throw new UnexpectedValueException("Unserialized data is not array");
        }
        return new Session($data, $id);
    }

    public function getIdGenerator() : callable
    {
        if (!$this->idGenerator) {
            $this->idGenerator = function () {
                return session_create_id();
            };
        }
        return $this->idGenerator;
    }

    public function setIdGenerator(callable $idGenerator) : SessionGuard
    {
        $this->idGenerator = $idGenerator;
        return $this;
    }

    protected function getOrCreateSessionId(HttpInput $input) : string
    {
        $cookieParams = $input->getCookieParams();
        $name = $this->getCookieName();
        if (isset($cookieParams[$name]) && $cookieParams[$name]) {
            return $cookieParams[$name];
        }
        return $this->generateId();
    }

    /**
     * Check if the session would be started on the passed request.
     *
     * @param Input $input
     * @return bool
     */
    protected function shouldHaveSession(Input $input) : bool
    {
        return $input instanceof HttpInput;
    }

    /**
     * @param array $config
     * @param string $sessionId
     * @param HttpInput $input
     * @return Cookie
     */
    protected function createCookie(array $config, string $sessionId, HttpInput $input) : Cookie
    {
        $defaultSecure = null;
        if($input->getUrl()->scheme && $input->getUrl()->scheme == 'http') {
            $defaultSecure = false;
        }
        return new Cookie(
            $config[self::COOKIE_NAME],
            $sessionId,
            isset($config['lifetime']) ? ($config['lifetime'] =='session' ? null : (int)$config['lifetime']) : $this->lifeTime,
            $config['path'] ?? null,
            $config['domain'] ?? null,
            $config['secure'] ?? $defaultSecure,
            $config['httponly'] ?? null,
            $config['samesite'] ?? null
        );
    }

    protected function hasCookie(HttpInput $input, string $name) : bool
    {
        return isset($input->cookie[$name]) && $input->cookie[$name];
    }
}