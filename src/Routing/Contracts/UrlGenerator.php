<?php
/**
 *  * Created by mtils on 26.10.2022 at 19:59.
 **/

namespace Koansu\Routing\Contracts;

use Koansu\Core\Url;
use Koansu\Routing\Route;
use Koansu\Routing\RouteScope;

/**
 * The Url Generator generates urls to parts of your application
 * It returns url objects so every extra parameter, path segment, fragment
 * etc. can be set on the returned object instead of passing them to the
 * method.
 **/
interface  UrlGenerator
{
    /**
     * The to() method accepts all kind of parameters. Passing an Entity
     * results in self::entity($path, 'show')
     * Passing a string will be used as path.
     *
     * @param string|object $path
     * @param string|RouteScope|null $scope
     *
     * @return Url
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function to($path, $scope=null) : Url;

    /**
     * This is similar to laravels UrlGenerator::route() method but also returns
     * an object.
     *
     * @param string|Route              $route
     * @param array                     $parameters (optional)
     * @param string|RouteScope|null    $scope
     *
     * @return Url
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function route($route, array $parameters = [], $scope=null) : Url;

    /**
     * Return the url to an entity action. Default action is show. Pass an array
     * for a path: resource([$user, 'addresses'])
     *
     * @param object|array          $entity
     * @param string                $action   (optional)
     * @param string|RouteScope|null $scope
     *
     * @return Url
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function entity($entity, string $action = 'show', $scope=null) : Url;

    /**
     * Return the url to an asset.
     *
     * @param string $path
     * @param string|RouteScope|null $scope
     *
     * @return Url
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function asset(string $path, $scope=null) : Url;

    /**
     * Get the applied input.
     *
     * @return Input
     */
    public function getInput() : Input;

    /**
     * Return a new instance for $input. Typically, you have one generator per
     * clientType or combination of clientType and scope.
     *
     * @param Input $input
     * @return UrlGenerator
     */
    public function withInput(Input $input) : UrlGenerator;
}
