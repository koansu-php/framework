<?php
/**
 *  * Created by mtils on 23.02.2023 at 08:50.
 **/

namespace Koansu\Routing\Middleware;

use ArrayAccess;
use Koansu\Core\Contracts\Serializer;
use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Core\Response;
use Koansu\Core\Serializers\XorObfuscator;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Exceptions\CsrfTokenException;
use Koansu\Routing\HttpInput;

use function bin2hex;
use function hash_equals;
use function in_array;
use function random_bytes;

class CsrfTokenMiddleware
{
    /**
     * @var array|ArrayAccess
     */
    protected $storage;

    /**
     * @var string[]
     */
    protected $methods = ['POST', 'PUT', 'DELETE', 'PATCH'];

    /**
     * @var string[]
     */
    protected $clientTypes = [Input::CLIENT_WEB, Input::CLIENT_AJAX, Input::CLIENT_CMS];

    /**
     * @var Serializer|null
     */
    protected static $obfuscator;

    protected static $sessionKey = 'csrf_token';

    protected static $parameterKey = '_csrf';

    protected static $tokenLength = 32;

    protected static $reuseTokens = true;

    protected static $allowHeader = true;
    protected static $headerName = 'X-Xsrf-Token';

    public function __invoke(Input $input, callable $next) : Response
    {
        if (!$input instanceof HttpInput) {
            return $next($input);
        }
        if (!in_array($input->getMethod(), $this->getMethods())) {
            return $next($input);
        }

        if ($input->getClientType() && !in_array($input->getClientType(), $this->getClientTypes())) {
            return $next($input);
        }

        if (!$storedToken = static::getTokenFromSession($input)) {
            throw new CsrfTokenException('Missing crsf token in session');
        }

        if (!$givenToken = static::getTokenFromInput($input)) {
            throw new CsrfTokenException('Missing crsf token in parameters');
        }

        if (static::validate($givenToken, $storedToken)) {
            return $next($input);
        }

        throw new CsrfTokenException();

    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @param string[] $methods
     * @return CsrfTokenMiddleware
     */
    public function setMethods(array $methods): CsrfTokenMiddleware
    {
        $this->methods = $methods;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getClientTypes(): array
    {
        return $this->clientTypes;
    }

    /**
     * @param string[] $clientTypes
     * @return CsrfTokenMiddleware
     */
    public function setClientTypes(array $clientTypes): CsrfTokenMiddleware
    {
        $this->clientTypes = $clientTypes;
        return $this;
    }

    /**
     * @return Serializer
     */
    public static function getObfuscator(): Serializer
    {
        if (!static::$obfuscator) {
            static::$obfuscator = new XorObfuscator([XorObfuscator::FIXED_LENGTH => static::$tokenLength]);
        }
        return static::$obfuscator;
    }

    /**
     * @param Serializer $obfuscator
     * @return CsrfTokenMiddleware
     */
    public static function setObfuscator(Serializer $obfuscator) : void
    {
        static::$obfuscator = $obfuscator;
    }

    /**
     * @return string
     */
    public static function getSessionKey(): string
    {
        return static::$sessionKey;
    }

    /**
     * @param string $sessionKey
     */
    public static function setSessionKey(string $sessionKey) : void
    {
        static::$sessionKey = $sessionKey;
    }

    /**
     * @return string
     */
    public static function getParameterKey(): string
    {
        return static::$parameterKey;
    }

    /**
     * @param string $parameterKey
     * @return CsrfTokenMiddleware
     */
    public static function setParameterKey(string $parameterKey): void
    {
        static::$parameterKey = $parameterKey;
    }

    /**
     * @return int
     */
    public static function getTokenLength(): int
    {
        return self::$tokenLength;
    }

    /**
     * @param int $tokenLength
     */
    public static function setTokenLength(int $tokenLength): void
    {
        self::$tokenLength = $tokenLength;
        if (static::$obfuscator instanceof XorObfuscator) {
            static::$obfuscator->setOption(XorObfuscator::FIXED_LENGTH, $tokenLength);
        }
    }

    public static function generate(int $length=0) : string
    {
        if ($length == 0) {
            $length = static::$tokenLength;
        }
        return bin2hex(random_bytes($length/2));
    }

    public static function validate(string $givenToken, string $storedToken) : bool
    {
        try {
            return hash_equals(static::unmaskToken($givenToken), $storedToken);
        } catch (DataIntegrityException $e) {
            return false;
        }

    }

    public static function getOrGenerate(HttpInput $input) : string
    {
        if (static::$reuseTokens && $token = static::getTokenFromSession($input)) {
            return $token;
        }
        $input->session[static::$sessionKey] = static::generate();
        return $input->session[static::$sessionKey];
    }

    public static function getTokenFromInput(HttpInput $input) : string
    {
        if ($token = $input->get(static::$parameterKey)) {
            return $token;
        }
        if ($token = $input->getHeaderLine(static::$headerName)) {
            return $token;
        }
        return '';
    }

    public static function getTokenFromSession(HttpInput $input) : string
    {
        if (isset($input->session[static::$sessionKey]) && $input->session[static::$sessionKey]) {
            return $input->session[static::$sessionKey];
        }
        return '';
    }

    public static function maskToken(string $token) : string
    {
        return static::getObfuscator()->serialize($token);
    }

    protected static function unmaskToken(string $maskedToken): string
    {
        return static::getObfuscator()->deserialize($maskedToken);
    }
}