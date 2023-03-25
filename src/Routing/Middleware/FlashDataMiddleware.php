<?php
/**
 *  * Created by mtils on 18.02.2023 at 10:15.
 **/

namespace Koansu\Routing\Middleware;

use Koansu\Core\Response;
use Koansu\Core\Url;
use Koansu\Http\HttpResponse;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;
use Koansu\Routing\Session;
use LogicException;
use UnexpectedValueException;

use function array_diff_key;
use function array_flip;
use function get_class;
use function random_bytes;

class FlashDataMiddleware
{
    public static $restoreParameter = '_restore_data_id';
    public static $sessionKey = '_flash_data';

    public static $inputKey = 'restored';

    /**
     * @var string[]
     */
    public static $ignoreKeys = ['payload','envelope', 'status'];

    public function __invoke(Input $input, callable $next) : Response
    {
        if (!$input instanceof HttpInput) {
            return $next($input);
        }

        $response = $next($this->restore($input));

        if (!$response instanceof HttpResponse) {
            throw new UnexpectedValueException('The response to HttpInput should be HttpResponse not ' . get_class($response));
        }

        if (!$this->isRedirect($response)) {
            return $response;
        }
        return $this->flash($response, $input->session);
    }

    protected function restore(HttpInput $input) : HttpInput
    {
        if (!$input->session) {
            throw new LogicException("FlashDataMiddleware must run after the session was added to the request");
        }
        if (!$restoreId = $input->get(static::$restoreParameter)) {
            return $input;
        }
        if (!isset($input->session[static::$sessionKey][$restoreId])) {
            return $input;
        }

        $flashData = $input->session[static::$sessionKey];
        // Flash the data out of the session
        $data = $flashData[$restoreId];
        unset($flashData[$restoreId]);

        $input->session[static::$sessionKey] = $flashData;

        return $input->with(static::$inputKey, $data);
    }

    protected function flash(HttpResponse $response, Session $session) : Response
    {
        if (!$response->custom) {
            return $response;
        }

        if (!$forwarded = array_diff_key($response->custom, array_flip(static::$ignoreKeys))) {
            return $response;
        }

        $flashData = isset($session[static::$sessionKey]) ? $session[static::$sessionKey] : [];
        $restoreId = $this->generateRestoreId();
        $flashData[$restoreId] = $forwarded;

        $session[static::$sessionKey] = $flashData;
        $location = $response->getHeaderLine('Location');
        $url = new Url($location);
        return $response->withHeader('Location', (string)$url->query(static::$restoreParameter, $restoreId));
    }

    protected function isRedirect(HttpResponse $response) : bool
    {
        return $response->getStatusCode() >= 300 && $response->getStatusCode() < 400;
    }

    protected function generateRestoreId() : string
    {
        return bin2hex(random_bytes(10));
    }
}