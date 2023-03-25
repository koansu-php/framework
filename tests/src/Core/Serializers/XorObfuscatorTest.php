<?php
/**
 *  * Created by mtils on 26.02.2023 at 09:20.
 **/

namespace Koansu\Tests\Core\Serializers;

use Koansu\Core\Contracts\Serializer;
use Koansu\Core\Exceptions\UnsupportedOptionException;
use Koansu\Core\Serializers\XorObfuscator;
use Koansu\Tests\TestCase;

use function bin2hex;
use function random_bytes;
use function strlen;
use function strpos;

class XorObfuscatorTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(Serializer::class, $this->make());
    }

    /**
     * @test
     **/
    public function it_throws_exception_if_serialize_without_secret_and_fixed_length()
    {
        $this->expectException(UnsupportedOptionException::class);
        $string = 'abcdefghijklmnopqrstuvwxyz';
        $obfuscator = $this->make();
        $obfuscator->serialize($string);
    }

    /**
     * @test
     */
    public function it_obfuscates_string_without_secret()
    {
        $string = 'abcdefghijklmnopqrstuvwxyz';
        $fixedLength = strlen($string);
        $obfuscator = $this->make([XorObfuscator::FIXED_LENGTH => $fixedLength]);

        $obfuscated = $obfuscator->serialize($string);
        $this->assertTrue(strlen($obfuscated) > $fixedLength);
        $this->assertFalse(strpos($obfuscated, $string));

        $plain = $obfuscator->deserialize($obfuscated);
        $this->assertEquals($string, $plain);
    }

    /**
     * @test
     */
    public function it_obfuscates_string_with_secret()
    {
        $string = 'abcdefghijklmnopqrstuvwxyz';
        $secret = bin2hex(random_bytes(16));
        $obfuscator = $this->make([XorObfuscator::SECRET => $secret]);

        $obfuscated = $obfuscator->serialize($string);
        $this->assertTrue(strlen($obfuscated) > strlen($string));
        $this->assertFalse(strpos($obfuscated, $string));

        $this->assertEquals($string, $obfuscator->deserialize($obfuscated));
    }

    /**
     * @test
     */
    public function it_obfuscates_array_with_secret()
    {
        $array = ['foo' => 'bar'];
        $secret = bin2hex(random_bytes(16));
        $obfuscator = $this->make([XorObfuscator::SECRET => $secret]);

        $obfuscated = $obfuscator->serialize($array);
        $this->assertTrue(strlen($obfuscated) > 16);

        $this->assertEquals($array, $obfuscator->deserialize($obfuscated));
    }

    protected function make(array $options=[]) : XorObfuscator
    {
        return new XorObfuscator($options);
    }
}