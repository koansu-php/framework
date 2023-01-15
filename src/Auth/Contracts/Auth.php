<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:47.
 **/

namespace Koansu\Auth\Contracts;

interface Auth
{
    public const ACCESS = 'access';
    public const MODIFY = 'modify';
    public const HIDE = 'hide';

    public const DELETE = 'delete';

    // The situation when nobody is authenticated (in a web app)
    public const NOBODY = 'nobody';
    // An alias for nobody
    public const GUEST = 'nobody';

    // The situation when a cronjob started or the system was anonymously executed
    // on console
    public const SYSTEM = 'system';

    // The "not special user" situation, just added for is() function
    public const USER = 'user';

    /**
     * @param array $credentials
     * @return object|null
     */
    public function userByCredentials(array $credentials) : ?object;

    /**
     * Return true if $subject can access $resource. $subject is mostly the user,
     * but you could also support roles, groups, ...
     *
     * @param object    $subject
     * @param mixed     $resource
     * @param string    $operation (default: access)
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function allowed(object $subject, $resource, string $operation=self::ACCESS) : bool;

    /**
     * Return true if the passed user is a user that can be interpreted as logged
     * in. This is not the case if it is the nobody user. If a cronjob started
     * the system or so decide for yourself what this is.
     * At the end this is a shortcut for !self::is($user, self::NOBODY)
     *
     * @param object $user
     * @return bool
     */
    public function isAuthenticated(object $user) : bool;

    /**
     * Return the "special" user $when nobody|system|.... When can be self::NOBODY,
     * self::SYSTEM etc. This is a user that is assigned if no real user is
     * authenticated.
     *
     * @param string $when
     * @return object
     */
    public function specialUser(string $when) : object;

    /**
     * Check if the passed $user is the special user $when.
     *
     * @param object $user
     * @param string $when
     * @return bool
     */
    public function is(object $user, string $when) : bool;
}