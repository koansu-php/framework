<?php
/**
 *  * Created by mtils on 11.09.2022 at 07:14.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\ConfigurableTrait;
use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\Exceptions\UnsupportedOptionException;
use Koansu\Tests\TestCase;

class ConfigurableTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(Configurable::class, $this->make());
    }

    /**
     * @test
     **/
    public function supportedOptions_returns_option_keys()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $this->assertEquals(array_keys($defaults), $this->make($defaults)->supportedOptions());
    }


    /**
     * @test
     **/
    public function getOption_returns_default_value()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);
        foreach ($defaults as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }
    }

    /**
     * @test
     **/
    public function setOption_overwrites_default_value()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);
        foreach ($defaults as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }
        $obj->setOption('prefix', 'bar_');
        $this->assertEquals('bar_', $obj->getOption('prefix'));
    }

    /**
     * @test
     **/
    public function setOption_overwrites_multiple_default_values()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);
        foreach ($defaults as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }
        $options = [
            'prefix'    => 'baz_',
            'retries'   => 15
        ];
        $this->assertSame($obj, $obj->setOption($options));
        foreach ($options as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }
        $this->assertEquals($defaults['encode'], $obj->getOption('encode'));
    }

    /**
     * @test
     */
    public function resetOptions_resets_one_key()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);

        $options = [
            'prefix'    => 'baz_',
            'retries'   => 15
        ];
        $this->assertSame($obj, $obj->setOption($options));
        foreach ($options as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }
        $this->assertEquals($defaults['encode'], $obj->getOption('encode'));

        $obj->resetOptions('prefix');
        $this->assertEquals($defaults['prefix'], $obj->getOption('prefix'));
        $this->assertEquals($options['retries'], $obj->getOption('retries'));
    }

    /**
     * @test
     */
    public function resetOptions_resets_multiple_keys()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);

        $options = [
            'prefix'    => 'baz_',
            'encode'    => false,
            'retries'   => 15
        ];
        $this->assertSame($obj, $obj->setOption($options));
        foreach ($options as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }

        $obj->resetOptions('prefix', 'retries');
        $this->assertEquals($defaults['prefix'], $obj->getOption('prefix'));
        $this->assertEquals($defaults['retries'], $obj->getOption('retries'));
        $this->assertEquals($options['encode'], $obj->getOption('encode'));
    }

    /**
     * @test
     */
    public function resetOptions_without_parameter_resets_all_keys()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);

        $options = [
            'prefix'    => 'baz_',
            'encode'    => false,
            'retries'   => 15
        ];
        $this->assertSame($obj, $obj->setOption($options));
        foreach ($options as $option=>$value) {
            $this->assertEquals($value, $obj->getOption($option));
        }

        $obj->resetOptions();
        foreach ($options as $option=>$value) {
            $this->assertEquals($defaults[$option], $obj->getOption($option));
        }
    }

    /**
     * @test
     **/
    public function get_unsupported_option_throws_exception()
    {
        $this->expectException(UnsupportedOptionException::class);
        $this->make()->getOption('foo');
    }

    /**
     * @test
     **/
    public function not_implementing_defaultOptions_throws_exception()
    {
        $this->expectException(ImplementationException::class);
        $obj = new class() {
            use ConfigurableTrait;
        };
        $obj->getOption('foo');
    }

    /**
     * @test
     **/
    public function mergeOptions_merges_passed_options()
    {
        $defaults = [
            'prefix'    => 'foo_',
            'encode'    => true,
            'retries'   => 3
        ];
        $obj = $this->make($defaults);

        $options = [
            'prefix'    => 'baz_',
            'retries'   => 15
        ];
        $obj->setOption($options);

        $passed = [
            'prefix' => 'bar_'
        ];

        $this->assertEquals([
            'prefix'    => $passed['prefix'],
            'encode'    => $defaults['encode'],
            'retries'   => $options['retries']
        ], $obj->run($passed));
    }

    protected function make(array $defaultOptions=[]) : Configurable
    {
        return new class ($defaultOptions) implements Configurable
        {
            use ConfigurableTrait;
            protected $defaultOptions = [];
            public function __construct(array $defaultOptions=[])
            {
                $this->defaultOptions = $defaultOptions;
            }
            public function run(array $options) : array
            {
                return $this->mergeOptions($options);
            }
        };
    }
}
