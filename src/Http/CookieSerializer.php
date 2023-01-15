<?php
/**
 *  * Created by mtils on 03.12.2022 at 13:51.
 **/

namespace Koansu\Http;

use DateTimeInterface;
use DateTimeZone;
use Koansu\Core\Contracts\Serializer;
use Koansu\Core\Exceptions\ImplementationException;
use UnexpectedValueException;

use function array_keys;
use function array_values;
use function rawurlencode;
use function str_replace;
use function time;
use function implode;

/**
 * Serialize a cookie into a header
 */
class CookieSerializer implements Serializer
{
    private const replace = [
        '='     => '%3D',
        ','     => '%2C',
        ';'     => '%3B',
        ' '     => '%20',
        "\t"    => '%09',
        "\r"    => '%0D',
        "\n"    => '%0A',
        "\v"    => '%0B',
        "\f"    => '%0C'
    ];

    public function mimeType() : string
    {
        return 'application/vnd.ems.http-cookie';
    }

    /**
     * @param Cookie $value
     * @param array $options
     * @return string
     */
    public function serialize($value, array $options = []) : string
    {
        if (!$value instanceof Cookie) {
            throw new UnexpectedValueException("I understand only objects of Cookie");
        }

        $cookie = $value;

        if ((string)$cookie->value === '') {
            return $this->encodeDelete($cookie);
        }

        $parts = [];
        $parts[] = $this->encodeName($cookie) . '=' . $this->encodeValue($cookie);

        if ($cookie->expire) {
            $parts[] = $this->encodeExpiry($cookie);
        }

        if ($cookie->path) {
            $parts[] = "path=$cookie->path";
        }

        if ($cookie->domain) {
            $parts[] = "domain=$cookie->domain";
        }

        if ($cookie->secure) {
            $parts[] = "secure";
        }

        if ($cookie->httpOnly) {
            $parts[] = "httponly";
        }

        if ($cookie->sameSite) {
            $parts[] = 'samesite=' . $cookie->sameSite;
        }

        return implode('; ', $parts);

    }

    /**
     * @param string $string
     * @param array $options
     * @return mixed
     * @throws ImplementationException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deserialize(string $string, array $options = [])
    {
        throw new ImplementationException("Deserializing is currently not supported and will be added to a later release");
    }

    /**
     * @param Cookie $cookie
     * @return string
     */
    public function __invoke(Cookie $cookie) : string
    {
        return $this->serialize($cookie);
    }

    protected function encodeName(Cookie $cookie) : string
    {
        $search  = array_keys(self::replace);
        $replace = array_values(self::replace);

        return str_replace($search, $replace, $cookie->name);
    }

    protected function encodeValue(Cookie $cookie) : string
    {
        return rawurlencode($cookie->value);
    }

    protected function encodeExpiry(Cookie $cookie) : string
    {
        $clone = clone $cookie->expire;
        $clone->setTimezone(new DateTimeZone('UTC'));
        $maxAge = $cookie->expire->getTimestamp() - time();

        $string = 'expires=' . $clone->format(DateTimeInterface::COOKIE);
        $string .= '; Max-Age=' . max(0, $maxAge);
        return $string;
    }

    protected function encodeDelete(Cookie $cookie) : string
    {
        return $this->encodeName($cookie) . '=deleted; expires=Thu, Jan 01 1970 00:00:00 UTC; Max-Age=-99999999';
    }
}