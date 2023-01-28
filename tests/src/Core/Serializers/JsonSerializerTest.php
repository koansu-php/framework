<?php /** @noinspection PhpStrFunctionsInspection */

/**
 *  * Created by mtils on 28.10.2022 at 11:55.
 **/

namespace Koansu\Tests\Core\Serializers;

use Koansu\Core\Contracts\Serializer as SerializerContract;
use Koansu\Core\Serializers\JsonSerializer;
use Koansu\Testing\Cheat;

use Koansu\Tests\TestCase;

use function is_string;
use function json_encode;
use function opendir;
use function sys_get_temp_dir;

use const JSON_PRETTY_PRINT;

class JsonSerializerTest extends TestCase
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
    public function serialize_and_deserialize_valid_values()
    {
        $serializer = $this->newSerializer();

        /** @noinspection SpellCheckingInspection */
        $tests = [
            [],
            [1,2,3],
            ['foo' => 'bar', 'baz' => 'bu' ],
            ['float' => 4.5],
            ['abcdeöäüüpouioß']
        ];

        foreach ($tests as $test) {
            $serialized = $serializer->serialize($test);
            $this->assertTrue(is_string($serialized));
            $deserialized = $serializer->deserialize($serialized, [JsonSerializer::AS_ARRAY=>true]);
            $this->assertEquals($test, $deserialized);
        }
    }

    /**
     * @test
     */
    public function serialize_with_depth_2()
    {
        $serializer = $this->newSerializer();

        $test = [
            'name'      => 'Michael',
            'address'   => [
                'street' => 'Elm Str.'
            ]
        ];

        $awaited = (object)[
            'name'      => 'Michael'
        ];


        $serialized = $serializer->serialize($test);

        $this->assertTrue(is_string($serialized));
        $deserialized = $serializer->deserialize($serialized, [JsonSerializer::DEPTH=>2]);

        $this->assertNull($deserialized);

    }

    /**
     * @test
     */
    public function serialize_pretty()
    {
        $serializer = $this->newSerializer();

        $test = [
            'name'      => 'Michael',
            'address'   => [
                'street' => 'Elm Str.'
            ]
        ];

        $awaited = (object)[
            'name'      => 'Michael'
        ];


        $serialized = $serializer->serialize($test, [JsonSerializer::PRETTY=>true]);
        $this->assertEquals(json_encode($test, JSON_PRETTY_PRINT), $serialized);

    }

    protected function newSerializer(callable $errorGetter=null) : JsonSerializer
    {
        return new JsonSerializer($errorGetter);
    }
}