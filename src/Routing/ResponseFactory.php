<?php /** @noinspection PhpStrFunctionsInspection */

/**
 *  * Created by mtils on 26.10.2022 at 20:03.
 **/

namespace Koansu\Routing;

use Koansu\Core\Notification;
use Koansu\Core\RenderData;
use Koansu\Core\Response;
use Koansu\Core\Serializers\JsonSerializer;
use Koansu\Core\Str;
use Koansu\Core\Url;
use Koansu\Http\HttpResponse;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\ResponseFactory as ResponseFactoryContract;
use Koansu\Routing\Contracts\UrlGenerator as UrlGeneratorContract;
use Koansu\Routing\Contracts\UtilizesInput;
use Koansu\Routing\View\Routing;
use Koansu\Validation\Contracts\Validation;
use LogicException;
use OutOfBoundsException;
use Psr\Log\LogLevel;
use Throwable;

use function count;
use function explode;
use function in_array;
use function interface_exists;


class ResponseFactory implements ResponseFactoryContract, UtilizesInput
{
    const CREATE = 'create';

    const UPDATE = 'update';

    const DELETE = 'delete';

    const VIEW = 'show';

    /**
     * @var Input
     */
    private $input;

    /**
     * @var UrlGeneratorContract
     */
    private $urls;

    public function __construct(UrlGeneratorContract $urls)
    {
        $this->urls = $urls;
    }

    /**
     * @param object|string $content
     * @return Response|HttpResponse
     */
    public function create($content): Response
    {
        $contentType = 'application/octet-stream';
        if ($content instanceof RenderData && $content->getMimeType()) {
            $contentType = $content->getMimeType();
        }

        if ($this->input && $this->input->getClientType() == Input::CLIENT_CONSOLE) {
            return new Response($content, [], 0, $contentType);
        }

        $response = new HttpResponse($content, [], 200, $contentType);
        $response->provideSerializerBy(function ($contentType) {
            if ($contentType == 'application/json') {
                return new JsonSerializer();
            }
            return null;
        });
        return $response;
    }

    /**
     * @param string $name
     * @param array $data
     * @return Response|HttpResponse
     */
    public function template(string $name, array $data = []): Response
    {
        $view = (new RenderData($name))->assign($data);
        if ($this->input) {
            $view->setMimeType($this->input->getDeterminedContentType());
        }
        return $this->create($view);
    }

    /**
     * @param Url|string $to
     * @param array $routeParams
     * @return Response
     */
    public function redirect($to, array $routeParams = []): Response
    {
        $to = $this->isRouteName($to) ? $this->urls->route($to, $routeParams) : $to;
        return new HttpResponse($this->redirectHtml($to),['Location' => "$to"], 302);
    }

    public function back(): Response
    {
        $input = $this->input;
        if (!$input instanceof HttpInput) {
            throw new LogicException('Assigned input is no HttpInput so dont know the previous page');
        }
        if ($sourceUrl = Routing::sourceUrl($input)) {
            return $this->redirect($sourceUrl);
        }
        throw new OutOfBoundsException('Unable to find the previous page to send the user back.');
    }

    public function forward($hint=null): Response
    {
        $input = $this->input;
        if (!$input instanceof HttpInput) {
            return $this->redirect('/');
        }

        if (!$this->isClientTypeForRedirects()) {
            return $this->autoCreateResponse($hint);
        }

        if ($nextUrl = Routing::nextUrl($input)) {
            return $this->redirect($nextUrl);
        }

        if (in_array($input->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            return $this->back();
        }

        if ($input->getMethod() == 'DELETE') {
            return $this->create('');
        }

        return $this->redirect('/');
    }

    protected function autoCreateResponse($hint=null)
    {
        if (!$this->input || !$hint) {
            return $this->create('')->withStatus(204);
        }

        $action = $this->guessAction($this->input);

        $status = $action == self::CREATE ? 201 : 200;

        if (!$resource = $this->guessResourceFromRoute($this->input->getMatchedRoute())) {
            return $this->create('')->withStatus($status);
        }

        $template = "$resource.show";
        return $this->template($template, [
            $resource   => [$hint]
        ])->withStatus($status);
    }


    public function error(Throwable $error=null) : Response
    {
        $data = [];

        $isValidationError = $this->isValidationError($error);

        if ($error && $error->getMessage()) {
            $level = $isValidationError ? LogLevel::WARNING : LogLevel::ERROR;
            $data['message'] = new Notification($error->getMessage(), $level);
        }

        if ($error && $failures = $this->extractErrors($error)) {
            $data['errors'] = $failures;
        }

        if (!$this->isClientTypeForRedirects()) {
            $status = $isValidationError ? 422 : 400;
            return $this->template('error', $data)->withStatus($status);
        }

        return $data ? $this->back()->with($data) : $this->back();
    }

    public function setInput(Input $input)
    {
        $this->input = $input;
        $this->urls = $this->urls->withInput($input);
    }

    protected function isClientTypeForRedirects() : bool
    {
        if (!$this->input instanceof HttpInput) {
            return false;
        }
        $clientType = $this->input->getClientType();
        return !$clientType || in_array($clientType, [Input::CLIENT_WEB, Input::CLIENT_CMS, Input::CLIENT_AJAX]);
    }

    protected function isValidationError(Throwable $e=null) : bool
    {
        return $e && interface_exists(Validation::class) && $e instanceof Validation;
    }

    protected function extractErrors(Throwable $e) : array
    {
        if (interface_exists(Validation::class) && $e instanceof Validation) {
            return $e->failures();
        }
        return [];
    }

    protected function guessAction(Input $input) : string
    {
        $action = '';
        if ($route = $input->getMatchedRoute()) {
            $action = $this->guessActionFromRoute($route);
        }
        if ($action) {
            return $action;
        }

        $method = $input->getMethod();

        if ($method == 'POST') {
            return self::CREATE;
        }

        if (in_array($method, ['PUT', 'PATCH'])) {
            return self::UPDATE;
        }

        if ($method == 'DELETE') {
            return self::DELETE;
        }
        return '';
    }

    protected function guessActionFromRoute(Route $route) : string
    {
        if ($this->routeEndsWith($route, ['store', 'create'])) {
            return self::CREATE;
        }
        if ($this->routeEndsWith($route, ['update','save', 'persist'])) {
            return self::UPDATE;
        }
        return '';
    }

    protected function guessResourceFromRoute(Route $route) : string
    {
        $parts = explode('.', $route->name);
        return count($parts) != 2 ? '' : $parts[0];
    }

    protected function routeEndsWith(Route $route, $suffixes) : bool
    {
        foreach ((array)$suffixes as $suffix) {
            if (Str::stringEndsWith($route->name, $suffix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the passed $to is a route name.
     *
     * @param string|Url $to
     * @return bool
     */
    protected function isRouteName($to) : bool
    {
        if ($to instanceof Url) {
            return false;
        }
        return strpos($to, '/') === false;
    }

    /**
     * @param string|Url $location
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection HtmlRequiredLangAttribute
     * @noinspection HtmlRequiredTitleElement
     */
    protected function redirectHtml($location) : string
    {
        return '<!DOCTYPE html>' .
               '<html>' .
                   '<head>' .
                       "<meta http-equiv=\"Refresh\" content=\"0; url=$location\" />" .
                   '</head>' .
               '</html>';
    }
}