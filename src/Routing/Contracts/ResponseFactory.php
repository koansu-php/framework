<?php
/**
 *  * Created by mtils on 26.10.2022 at 19:52.
 **/

namespace Koansu\Routing\Contracts;

use Koansu\Core\Url;
use Koansu\Core\Response;

interface ResponseFactory
{

    /**
     * @param string|object $content
     * @return Response
     * @noinspection PhpMissingParamTypeInspection
     */
    public function create($content) : Response;

    /**
     * Create the view $name with $data and output it.
     *
     * @param string $name
     * @param array $data (optional)
     * @return Response
     */
    public function template(string $name, array $data=[]) : Response;

    /**
     * Return a direct to $to. Pass an url for manual urls, a string for route
     * names
     *
     * @param string|Url $to
     * @param array      $routeParams
     * @return Response
     * @noinspection PhpMissingParamTypeInspection
     */
    public function redirect($to, array $routeParams=[]) : Response;

    /**
     * Find the next page after a write operation.
     *
     * @param mixed $hint Some hint to find the forward like a created model
     * @return Response
     * @noinspection PhpMissingParamTypeInspection
     */
    public function forward($hint=null) : Response;

    /**
     * Try to redirect the user back to the previous page.
     *
     * @return Response
     */
    public function back() : Response;

    /**
     * Show an error or send the user back to the page (form) to try the request
     * again. To send a user back the form has to send a handle, so we are sure
     * we hit exactly the request that send the user here.
     *
     * @return Response
     */
    public function error() : Response;
}