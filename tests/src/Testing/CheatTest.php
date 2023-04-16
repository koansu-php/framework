<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 *  * Created by mtils on 08.10.2022 at 08:32.
 **/

namespace Koansu\Tests\Testing;

use Koansu\Testing\Cheat;
use Koansu\Tests\TestCase;

use function func_get_args;

class CheatTest extends TestCase
{
    /**
     * @test
     */
    public function get_returns_public_value()
    {
        $tester = new VisibilityTester();
        $tester->public = 'a';
        $this->assertEquals('a', Cheat::get($tester, 'public'));
    }

    /**
     * @test
     */
    public function get_returns_protected_value()
    {
        $tester = new VisibilityTester();
        $tester->setProtected('a');
        $this->assertEquals('a', Cheat::get($tester, 'protected'));
    }

    /**
     * @test
     */
    public function get_returns_private_value()
    {
        $tester = new VisibilityTester();
        $tester->setPrivate('a');
        $this->assertEquals('a', Cheat::get($tester, 'private'));
    }

    /**
     * @test
     */
    public function set_changes_private_value()
    {
        $tester = new VisibilityTester();

        Cheat::set($tester, 'private', 'foobar');

        $this->assertEquals('foobar', $tester->getPrivate());
    }

    /**
     * @test
     */
    public function call_calls_public_method()
    {
        $tester = new VisibilityTester();
        $args = ['a','b','c'];
        $this->assertEquals('public', Cheat::call($tester, 'publicMethod', $args));
        $this->assertEquals($args, $tester->publicMethodArgs);
    }

    /**
     * @test
     */
    public function call_calls_protected_method()
    {
        $tester = new VisibilityTester();
        $args = ['a','b','c'];
        $this->assertEquals('protected', Cheat::call($tester, 'protectedMethod', $args));
        $this->assertEquals($args, $tester->protectedMethodArgs);
    }

    /**
     * @test
     */
    public function call_calls_private_method()
    {
        $tester = new VisibilityTester();
        $args = ['a','b','c'];
        $this->assertEquals('private', Cheat::call($tester, 'privateMethod', $args));
        $this->assertEquals($args, $tester->privateMethodArgs);
    }

}

class VisibilityTester
{
    public $public;

    protected $protected;

    private $private;

    public $publicMethodArgs;

    public $protectedMethodArgs;

    public $privateMethodArgs;

    public function getProtected()
    {
        return $this->protected;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setProtected($value): self
    {
        $this->protected = $value;
        return $this;
    }

    public function getPrivate()
    {
        return $this->private;
    }

    public function setPrivate($value): self
    {
        $this->private = $value;
        return $this;
    }

    public function publicMethod() : string
    {
        $this->publicMethodArgs = func_get_args();
        return 'public';
    }

    protected function protectedMethod() : string
    {
        $this->protectedMethodArgs = func_get_args();
        return 'protected';
    }

    private function privateMethod() : string
    {
        $this->privateMethodArgs = func_get_args();
        return 'private';
    }
}