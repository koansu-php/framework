<?php
/**
 *  * Created by mtils on 26.02.2023 at 08:57.
 **/

namespace Koansu\Core\Serializers;

use Koansu\Core\ConfigurableTrait;
use Koansu\Core\Contracts\Configurable;
use Koansu\Core\Contracts\Serializer;
use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Core\Exceptions\UnsupportedOptionException;
use LogicException;

use function base64_decode;
use function base64_encode;
use function is_string;
use function random_bytes;
use function serialize;
use function strlen;
use function substr;
use function unserialize;

/**
 * This is sample class to implement encryption by serialize interface. It is
 * used to simply obfuscate data so is not immediately readable
 */
class XorObfuscator implements Serializer, Configurable
{
    use ConfigurableTrait;

    public const FIXED_LENGTH = 'fixed_length';

    public const SECRET = 'secret';

    /**
     * @var Serializer|null
     */
    protected $serializer;

    protected $defaultOptions = [
        self::FIXED_LENGTH  => 0,
        self::SECRET        => ''
    ];

    public function __construct(array $options=[])
    {
        $this->setOption($options);
    }

    public function mimeType(): string
    {
        return 'application/octet-stream';
    }

    public function serialize($value, array $options = []): string
    {
        $options = $this->mergeOptions($options);
        if (!$options[self::FIXED_LENGTH] && !$options[self::SECRET]) {
            throw new UnsupportedOptionException('You have to either pass a fixed length or a secret to obfuscate the data');
        }

        if ($options[self::SECRET]) {
            $serialized = $this->needsSerialization($value) ? $this->serializeNonString($value) : $value;
            return base64_encode($this->xorCipher($serialized, $options[self::SECRET]));
        }

        if ($this->needsSerialization($value)) {
            throw new LogicException("Obfuscate with a fixed length can only work with strings");
        }

        if (strlen($value) != $options[self::FIXED_LENGTH]) {
            throw new LogicException("The string '$value' has not the fixed length of {$options[self::FIXED_LENGTH]} (by strlen no multibyte here)");
        }

        $salt = $this->generateSalt($options[self::FIXED_LENGTH]);
        return base64_encode($salt . ($salt ^ $value));

    }

    public function deserialize(string $string, array $options = [])
    {
        $options = $this->mergeOptions($options);

        if (!$options[self::FIXED_LENGTH] && !$options[self::SECRET]) {
            throw new UnsupportedOptionException('You have to either pass a fixed length or a secret to obfuscate the data');
        }

        if ($options[self::FIXED_LENGTH]) {
            return $this->deObfuscateWithFixedLength($string, $options[self::FIXED_LENGTH]);
        }

        $serialized = $this->xorCipher(base64_decode($string, true), $options[self::SECRET]);

        return $this->deserializePlain($serialized);
    }

    protected function deObfuscateWithFixedLength(string $string, int $length) : string
    {
        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            throw new DataIntegrityException('The data provided could not be decoded');
        }
        // Is a float
        if ((strlen($decoded) / 2) != $length) {
            throw new DataIntegrityException('The data provided could not be deciphered (checksum)');
        }
        $salt = substr($decoded, 0, $length);
        $decodedData = substr($decoded, $length, $length);

        return $salt ^ $decodedData;
    }

    protected function xorCipher(string $original, string $secret) : string
    {
        $converted = '';
        $originalLength = strlen($original);
        $secretLength = strlen($secret);

        for($i=0; $i<$originalLength;) {
            for($j=0; ($j<$secretLength && $i<$originalLength); $j++,$i++) {
                $converted .= $original[$i] ^ $secret[$j];
            }
        }
        return $converted;
    }

    protected function needsSerialization($value) : bool
    {
        return !is_string($value);
    }

    protected function serializeNonString($value) : string
    {
        if ($this->serializer) {
            return $this->serializer->serialize($value);
        }
        return serialize($value);
    }

    protected function deserializePlain(string $data)
    {
        if ($this->serializer) {
            return $this->serializer->deserialize($data);
        }

        if ($data === 'b:0;') {
            return false;
        }

        $deserialized = @unserialize($data);

        return $deserialized === false ? $data : $deserialized;

    }

    protected function generateSalt(int $length) : string
    {
        return random_bytes($length);
    }
}