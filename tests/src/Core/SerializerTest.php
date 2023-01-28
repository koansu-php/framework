<?php
/**
 *  * Created by mtils on 28.10.2022 at 10:06.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\Contracts\Serializer as SerializerContract;
use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Core\Serializer;
use Koansu\Tests\TestCase;
use Koansu\Testing\Cheat;

use LogicException;
use TypeError;

use function opendir;
use function realpath;
use function sys_get_temp_dir;

class SerializerTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(SerializerContract::class, $this->newSerializer());
    }

    /**
     * @test
     */
    public function mimeType_returns_string()
    {
        $mimeType = $this->newSerializer()->mimeType();
        $this->assertTrue(is_string($mimeType));
        $this->assertTrue(strpos($mimeType, '/') !== false);
    }

    /**
     * @test
     */
    public function serialize_and_deserialize_scalar_values()
    {
        $serializer = $this->newSerializer();

        /** @noinspection SpellCheckingInspection */
        $tests = [
            1,
            0,
            true,
            4.5,
            'abcdeöäüüpouioß'
        ];

        foreach ($tests as $test) {
            $serialized = $serializer->serialize($test);
            $this->assertTrue(is_string($serialized));
            $this->assertEquals($test, $serializer->deserialize($serialized));
        }
    }

    /**
     * @test
     */
    public function serializing_of_resource_throws_exception()
    {
        $serializer = $this->newSerializer();
        $res = opendir(sys_get_temp_dir());
        $this->expectException(TypeError::class);
        $serializer->serialize($res);
    }

    /**
     * @test
     */
    public function serializing_of_special_false_string_throws_exception()
    {
        $serializer = $this->newSerializer();
        $falseString = Cheat::get($serializer, 'serializeFalseAs');
        $this->expectException(LogicException::class);
        $serializer->serialize($falseString);
    }

    /**
     * @test
     */
    public function serializing_of_false_throws_no_exception()
    {
        $serializer = $this->newSerializer();
        $this->assertSame(false, $serializer->deserialize($serializer->serialize(false)));
    }

    /**
     * @test
     */
    public function test_deserializing_of_malformed_string_throws_exception_without_error()
    {
        $hook = function () { return false; };
        $serializer = $this->newSerializer($hook);
        $this->expectException(DataIntegrityException::class);
        $serializer->deserialize('foo');
    }

    /**
     * @test
     */
    public function test_deserializing_of_malformed_string_throws_exception()
    {
        $serializer = $this->newSerializer();
        $this->expectException(DataIntegrityException::class);
        $serializer->deserialize('foo');
    }

    /**
     * @test
     */
    public function unserializeError_guesses_error()
    {
        $hook = function () { return false; };

        $serializer = $this->newSerializer($hook);
        $this->assertFalse(Cheat::call($serializer, 'unserializeError'));

        $hook = function () {
            return [
                'file'    => 'Some not existing path',
                'message' => 'unserialize(): error'
            ];
        };

        $serializer = $this->newSerializer($hook);

        $this->assertFalse(Cheat::call($serializer, 'unserializeError'));

        $hook = function () {
            return [
                'file'    => realpath('src/Ems/Core/Serializer.php'),
                'message' => 'session_start(): error'
            ];
        };

        $serializer = $this->newSerializer($hook);

        $this->assertFalse(Cheat::call($serializer, 'unserializeError'));
    }

    protected function newSerializer(callable $errorGetter=null) : Serializer
    {
        return new Serializer($errorGetter);
    }
}