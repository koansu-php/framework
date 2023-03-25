<?php
/**
 *  * Created by mtils on 30.10.2022 at 17:28.
 **/

namespace Koansu\Tests\Routing;

use Koansu\Routing\Session;
use Koansu\Tests\TestCase;

use function serialize;
use function time;

class SessionTest extends TestCase
{
    /**
     * @test
     */
    public function it_instantiates()
    {
        $this->assertInstanceOf(Session::class, $this->session());
    }

    /**
     * @test
     */
    public function get_and_set_data()
    {
        $id = 'ABCD';
        $data = ['foo' => 'bar'];
        $sessions = [
            $id => [
                'data' => serialize($data),
                'updated' => time()
            ]
        ];

        $session = $this->session($data);
        $session->setId($id);
        $this->assertEquals('bar', $session['foo']);

        $session['a'] = 'b';
        $session->clear(['foo']);

    }

    /**
     * @test
     */
    public function clear_deletes_keys()
    {
        $session = $this->session();
        $session['a'] = 'b';
        $session['c'] = 'd';
        $session['e'] = 'f';
        $this->assertEquals(['a'=>'b','c'=>'d', 'e'=>'f'], $session->__toArray());
        $session->clear(['a','e']);
        $this->assertEquals(['c'=>'d'], $session->__toArray());
    }

    /**
     * @test
     */
    public function clear_deletes_all()
    {
        $session = $this->session();
        $session['a'] = 'b';
        $session['c'] = 'd';
        $session['e'] = 'f';
        $this->assertEquals(['a'=>'b','c'=>'d', 'e'=>'f'], $session->__toArray());
        $session->clear();
        $this->assertSame([], $session->__toArray());
    }

    /**
     * @test
     */
    public function clear_does_not_delete_if_empty_array_passed()
    {
        $session = $this->session();
        $session['a'] = 'b';
        $session['c'] = 'd';
        $session['e'] = 'f';
        $this->assertEquals(['a'=>'b','c'=>'d', 'e'=>'f'], $session->__toArray());
        $session->clear([]);
        $this->assertEquals(['a'=>'b','c'=>'d', 'e'=>'f'], $session->__toArray());
    }

    protected function session(array $data=[], string $id='') : Session
    {
        return new Session($data, $id);
    }

}
