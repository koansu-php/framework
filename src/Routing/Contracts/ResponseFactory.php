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

}