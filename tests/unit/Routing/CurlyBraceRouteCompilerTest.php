<?php
/**
 *  * Created by mtils on 26.10.2022 at 10:29.
 **/

namespace Koansu\Tests\Routing;

use Koansu\Routing\CurlyBraceRouteCompiler;
use Koansu\Tests\TestCase;

class CurlyBraceRouteCompilerTest extends TestCase
{
    /**
     * @test
     */
    public function it_instantiates()
    {
        $this->assertInstanceOf(CurlyBraceRouteCompiler::class, $this->make());
    }

    /**
     * @test
     */
    public function replaceWildcards_replaces_wildcards()
    {
        $c = $this->make();

        $tests = [
            'addresses/44' => [
                'addresses/{}', [44]
            ],
            'users/1785/addresses/3/edit' => [
                'users/{}/addresses/{}/edit', [1785,3]
            ],
            'users/3/addresses/{}/edit' => [
                'users/{}/addresses/{}/edit', [3]
            ]

        ];

        foreach ($tests as $expected=>$route) {
            $this->assertEquals($expected, $c->replaceWildcards($route[0], $route[1]));
        }

    }

    /**
     * @test
     */
    public function replaceNamed_replaces_parameters()
    {
        $c = $this->make();

        $tests = [
            'addresses/44' => [
                'addresses/{address_id}', ['address_id' => 44]
            ],
            'users/1785/addresses/3/edit' => [
                'users/{user_id}/addresses/{address_id}/edit', ['user_id' => 1785, 'address_id' => 3]
            ],
            'users/3/addresses/{address_id}/edit' => [
                'users/{user_id}/addresses/{address_id}/edit', ['user_id' => 3]
            ]

        ];

        foreach ($tests as $expected=>$route) {
            $this->assertEquals($expected, $c->replaceNamed($route[0], $route[1]));
        }

    }

    /**
     * @test
     */
    public function compile_replaces_parameters()
    {
        $c = $this->make();

        $tests = [
            'addresses/44' => [
                'addresses/{}', [44]
            ],
            'users/1785/addresses/3/edit' => [
                'users/{}/addresses/{}/edit', [1785,3]
            ],
            'users/3/addresses/{}/edit' => [
                'users/{}/addresses/{}/edit', [3]
            ]

        ];

        foreach ($tests as $expected=>$route) {
            $this->assertEquals($expected, $c->compile($route[0], $route[1]));
        }

        $tests = [
            'addresses/44' => [
                'addresses/{address_id}', ['address_id' => 44]
            ],
            'users/1785/addresses/3/edit' => [
                'users/{user_id}/addresses/{address_id}/edit', ['user_id' => 1785, 'address_id' => 3]
            ],
            'users/3/addresses/{address_id}/edit' => [
                'users/{user_id}/addresses/{address_id}/edit', ['user_id' => 3]
            ]

        ];

        foreach ($tests as $expected=>$route) {
            $this->assertEquals($expected, $c->compile($route[0], $route[1]));
        }

    }

    protected function make() : CurlyBraceRouteCompiler
    {
        return new CurlyBraceRouteCompiler();
    }
}