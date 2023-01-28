<?php
/**
 *  * Created by mtils on 20.12.2022 at 22:07.
 **/

namespace Koansu\Tests\Auth;

use Koansu\Auth\Auth;
use Koansu\Auth\User;
use Koansu\Tests\TestCase;
use Koansu\Auth\Contracts\Auth as AuthInterface;
use Koansu\Search\FilterableArray;

class AuthTest extends TestCase
{
    /**
    * @test
    */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(AuthInterface::class, $this->auth());
    }

    /**
     * @test
     */
    public function userByCredentials_loads_user()
    {
        $user = $this->auth()->userByCredentials(['id' => 2]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(2, $user->id);
    }

    /**
     * @test
     */
    public function specialUser_returns_users()
    {
        $auth = $this->auth();
        $auth->setCredentialsForSpecialUser(AuthInterface::GUEST, ['email' => 'nobody@somewhere.com']);
        $auth->setCredentialsForSpecialUser(AuthInterface::SYSTEM, ['email' => 'system@somewhere.com']);

        $nobody = $auth->specialUser(AuthInterface::GUEST);
        $this->assertInstanceOf(User::class, $nobody);
        $this->assertEquals(1, $nobody->id);

        $system = $auth->specialUser(AuthInterface::SYSTEM);
        $this->assertInstanceOf(User::class, $system);
        $this->assertEquals(2, $system->id);
    }

    /**
     * @test
     */
    public function is_returns_true_if_user_is_special_user_of_passed_type()
    {
        $auth = $this->auth();
        $auth->setCredentialsForSpecialUser(AuthInterface::GUEST, ['email' => 'nobody@somewhere.com']);
        $auth->setCredentialsForSpecialUser(AuthInterface::SYSTEM, ['email' => 'system@somewhere.com']);

        $nobody = $auth->specialUser(AuthInterface::GUEST);
        $system = $auth->specialUser(AuthInterface::SYSTEM);
        $user = $auth->userByCredentials(['email' => 'mary@somewhere.com']);

        $this->assertTrue($auth->is($nobody, AuthInterface::GUEST));
        $this->assertFalse($auth->is($nobody, AuthInterface::SYSTEM));
        $this->assertFalse($auth->is($nobody, AuthInterface::USER));
        $this->assertFalse($auth->is($system, AuthInterface::GUEST));
        $this->assertTrue($auth->is($system, AuthInterface::SYSTEM));
        $this->assertFalse($auth->is($user, AuthInterface::GUEST));
        $this->assertFalse($auth->is($user, AuthInterface::SYSTEM));
        $this->assertTrue($auth->is($user, AuthInterface::USER));

    }

    /**
     * @test
     */
    public function isAuthenticated_returns_true_on_non_guest()
    {
        $auth = $this->auth();
        $auth->setCredentialsForSpecialUser(AuthInterface::GUEST, ['email' => 'nobody@somewhere.com']);
        $auth->setCredentialsForSpecialUser(AuthInterface::SYSTEM, ['email' => 'system@somewhere.com']);

        $nobody = $auth->specialUser(AuthInterface::GUEST);
        $system = $auth->specialUser(AuthInterface::SYSTEM);
        $user = $auth->userByCredentials(['email' => 'mary@somewhere.com']);

        $this->assertFalse($auth->isAuthenticated($nobody));
        $this->assertTrue($auth->isAuthenticated($system));
        $this->assertTrue($auth->isAuthenticated($user));

    }

    /**
     * @test
     */
    public function allowed_forwards_to_added_callables()
    {
        $auth = $this->auth();
        $auth->setCredentialsForSpecialUser(AuthInterface::GUEST, ['email' => 'nobody@somewhere.com']);
        $auth->setCredentialsForSpecialUser(AuthInterface::SYSTEM, ['email' => 'system@somewhere.com']);

        $auth->addChecker(function ($subject, $resource, $operation) use ($auth) {
            if (!$subject instanceof User || $resource != 'cms.publish') {
                return null;
            }
            return $auth->isAuthenticated($subject);
        });

        $auth->addChecker(function ($subject, $resource, $operation) use ($auth) {
            if (!$subject instanceof User || !$resource instanceof User) {
                return null;
            }
            return $subject->id == $resource->id;
        });

        $user = $auth->userByCredentials(['email' => 'kathleen@somewhere.com']);
        $nobody = $auth->specialUser(AuthInterface::GUEST);

        $this->assertTrue($auth->allowed($user, 'cms.publish'));
        $this->assertFalse($auth->allowed($user, 'cms.delete'));
        $this->assertFalse($auth->allowed($nobody, 'cms.publish'));
        $this->assertTrue($auth->allowed($nobody, $nobody));
        $this->assertFalse($auth->allowed($user, $nobody));
        $this->assertFalse($auth->allowed($nobody, $user));
        $this->assertTrue($auth->allowed($user, $user));
    }

    protected function auth(callable $userProvider=null) : Auth
    {
        $userProvider = $userProvider ?: function (array $credentials) {
            $provider = $this->userProvider();
            $results = $provider->filter($credentials)->__toArray();
            return $results[0] ?? null;
        };
        return new Auth($userProvider);
    }

    protected function userProvider() : FilterableArray
    {
        return new FilterableArray($this->users());
    }

    /**
     * @return User[]
     */
    protected function users() : array
    {
        return [
            new User(['id' => 1, 'email' => 'nobody@somewhere.com']),
            new User(['id' => 2, 'email' => 'system@somewhere.com']),
            new User(['id' => 3, 'email' => 'mary@somewhere.com']),
            new User(['id' => 4, 'email' => 'peter@somewhere.com']),
            new User(['id' => 5, 'email' => 'kathleen@somewhere.com']),
        ];
    }
}