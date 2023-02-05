<?php
/**
 *  * Created by mtils on 05.02.2023 at 10:36.
 **/

namespace Koansu\Tests\Validation;

use Koansu\Core\Contracts\Extendable;
use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\Tests\TestCase;
use Koansu\Validation\ValidationConverter;
use Koansu\Validation\Exceptions\ValidationException;

class ValidationConverterTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(ValidationConverter::class, $this->newConverter());
        $this->assertInstanceOf(Extendable::class, $this->newConverter());
    }

    /**
     * @test
     */
    public function converter_returns_extensions_result()
    {

        $converter = $this->newConverter();
        $converter->extend('foo', function ($validation) {
            return 'bar';
        });

        $this->assertEquals('bar', $converter->convert($this->newValidation(), 'foo'));
    }

    /**
     * @test
     */
    public function test_convert_throws_exception_if_extension_not_found()
    {
        $converter = $this->newConverter();
        $this->expectException(HandlerNotFoundException::class);
        $converter->convert($this->newValidation(), 'foo');
    }

    protected function newValidation(array $failures = [], array $rules = [], $validatorClass=null) : ValidationException
    {
        return new ValidationException($failures, $rules, $validatorClass);
    }

    protected function newConverter() : ValidationConverter
    {
        return new ValidationConverter();
    }
}