<?php
/**
 *  * Created by mtils on 18.02.2023 at 14:09.
 **/

namespace Koansu\Routing\View;

use Koansu\Core\Message;
use Koansu\Core\Url;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Middleware\CsrfTokenMiddleware;
use Koansu\Routing\Middleware\FlashDataMiddleware;
use Koansu\Routing\HttpInput;
use OutOfBoundsException;

use function array_diff_key;
use function array_flip;
use function array_merge;
use function iterator_to_array;

/**
 * This is a helper class to use in views
 */
class Routing
{
    public static $sourceUrlKey = '_sourceUrl';

    public static $nextUrlKey = '_nextUrl';

    public static $methodKey = '_method';

    /**
     * Return the plain data from a request without special parameters.
     *
     * @param Input $input
     * @param string|string[] $alsoExclude
     * @return array
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function data(Input $input, $alsoExclude=[]) : array
    {
        $all = iterator_to_array($input);
        $known = [self::$sourceUrlKey, self::$nextUrlKey, self::$methodKey];
        return array_diff_key($all, array_flip(array_merge($known, (array)$alsoExclude)));
    }

    public static function sourceUrl(Input $input) : Url
    {
        if (!$source = self::getSourceUrlFromInput($input)) {
            throw new OutOfBoundsException('Unable to get source url out of input');
        }
        return new Url($source);
    }

    public static function sourceUrlField(Input $input) : string
    {
        return '<input type="hidden" name="' . self::$sourceUrlKey . '" value="' . $input->getUrl() . '"/>';
    }

    public static function nextUrl(Input $input) : ?Url
    {
        if (!isset($input[self::$nextUrlKey]) || !$input[self::$nextUrlKey]) {
            return null;
        }
        return $input[self::$nextUrlKey] instanceof Url ? $input[self::$nextUrlKey] : new Url($input[self::$nextUrlKey]);
    }

    public static function nextUrlField(Url $url) : string
    {
        return '<input type="hidden" name="' . self::$nextUrlKey . '" value="' . $url . '"/>';
    }

    public static function methodField(string $method='PUT') : string
    {
        return '<input type="hidden" name="' . static::$methodKey . '" value="' . $method . '"/>';
    }

    public static function storedData(Input $input) : array
    {
        if (!$input instanceof Message) {
            return [];
        }
        if (!$flashData = $input->getFrom(Message::POOL_CUSTOM, FlashDataMiddleware::$inputKey)) {
            return [];
        }

        $cleaned = [];
        foreach ($flashData as $key=>$value) {
            if ($key[0] != '_') {
                $cleaned[$key] = $value;
            }
        }
        return $cleaned;
    }

    public static function oldInput(Input $input) : array
    {
        if (!$data = static::storedData($input)) {
            return [];
        }
        return $data['input'] ?? [];
    }

    public static function errors(Input $input) : array
    {
        if (!$data = static::storedData($input)) {
            return [];
        }
        return $data['errors'] ?? [];
    }

    public static function messages(Input $input) : array
    {
        if (!$data = static::storedData($input)) {
            return [];
        }
        if (isset($data['messages'])) {
            return $data['messages'];
        }
        if (isset($data['message'])) {
            return [$data['message']];
        }
        return [];
    }

    public static function csrf(Input $input) : string
    {
        if (!$input instanceof HttpInput) {
            return '';
        }
        return CsrfTokenMiddleware::maskToken(CsrfTokenMiddleware::getOrGenerate($input));
    }

    public static function csrfField(Input $input) : string
    {
        return '<input type="hidden" name="' . CsrfTokenMiddleware::getParameterKey() . '" value="' . self::csrf($input) . '"/>';
    }

    protected static function getSourceUrlFromInput(Input $input) : string
    {
        if (isset($input[self::$sourceUrlKey]) && $input[self::$sourceUrlKey]) {
            return $input[self::$sourceUrlKey];
        }
        if (!$input instanceof HttpInput) {
            return '';
        }
        return $input->getHeaderLine('Referer');
    }
}