<?php /** @noinspection PhpStrFunctionsInspection */

/**
 *  * Created by mtils on 26.10.2022 at 20:03.
 **/

namespace Koansu\Routing;

use Koansu\Core\RenderData;
use Koansu\Core\Response;
use Koansu\Core\Serializers\JsonSerializer;
use Koansu\Core\Url;
use Koansu\Http\HttpResponse;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\ResponseFactory as ResponseFactoryContract;
use Koansu\Routing\Contracts\UrlGenerator as UrlGeneratorContract;
use Koansu\Routing\Contracts\UtilizesInput;


class ResponseFactory implements ResponseFactoryContract, UtilizesInput
{
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
        $attributes = ['payload' => $content];

        if ($content instanceof RenderData && $content->getMimeType()) {
            $attributes['contentType'] = $content->getMimeType();
        }

        if ($this->input && $this->input->getClientType() == Input::CLIENT_CONSOLE) {
            return new Response($attributes);
        }

        $response = new HttpResponse($attributes);
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
        $to = $this->isRouteName($to) ? $this->urls->route($to) : $to;
        return new HttpResponse($this->redirectHtml($to),['Location' => "$to"], 302);
    }

    public function setInput(Input $input)
    {
        $this->input = $input;
        $this->urls = $this->urls->withInput($input);
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