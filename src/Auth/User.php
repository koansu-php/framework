<?php
/**
 *  * Created by mtils on 20.12.2022 at 21:54.
 **/

namespace Koansu\Auth;

/**
 * This is a placeholder for your user object. Ems is not interested what your
 * user is.
 */
class User
{
    public $id = '';
    public $email = '';

    public function __construct(array $properties=[])
    {
        $this->id = $properties['id'] ?? '';
        $this->email = $properties['email'] ?? '';
    }
}