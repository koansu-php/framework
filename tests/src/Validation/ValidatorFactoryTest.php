<?php
/**
 *  * Created by mtils on 05.02.2023 at 12:07.
 **/

namespace Koansu\Tests\Validation;

use InvalidArgumentException;
use Koansu\Core\Contracts\Subscribable;
use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\Fakes\NamedObject;
use Koansu\Tests\TestCase;
use Koansu\Validation\Contracts\Validation;
use Koansu\Validation\Contracts\ValidatorFactory as ValidatorFactoryContract;
use Koansu\Validation\Exceptions\ValidationException;
use Koansu\Validation\Exceptions\ValidatorFabricationException;
use Koansu\Validation\Validator;
use Koansu\Validation\ValidatorFactory;
use stdClass;

class ValidatorFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(
            ValidatorFactoryContract::class,
            $this->newFactory()
        );
        $this->assertInstanceOf(
            SupportsCustomFactory::class,
            $this->newFactory()
        );
        $this->assertInstanceOf(Subscribable::class, $this->newFactory());
    }

    /**
     * @test
     */
    public function create_makes_validator()
    {
        $rules = [
            'login' => 'required|min:3'
        ];
        $parsed = [
            'login' => [
                'required' => [],
                'min' => [3]
            ]
        ];
        $validator = $this->newFactory()->create($rules, self::class);
        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertEquals($parsed, $validator->rules());
        $this->assertEquals(self::class, $validator->ormClass());
    }

    /**
     * @test
     */
    public function create_validator_calls_listener()
    {
        $rules = [
            'login' => 'required|min:3'
        ];
        $listener = new LoggingCallable();
        $factory = $this->newFactory();
        $factory->on(self::class, $listener);
        $validator = $factory->create($rules, self::class);
        $this->assertInstanceOf(Validator::class, $validator);

        $this->assertCount(1, $listener);
        $this->assertSame($validator, $listener->arg(0));
    }

    /**
     * @test
     */
    public function create_validator_without_ormClass_does_not_call_listener()
    {
        $rules = [
            'login' => 'required|min:3'
        ];
        $listener = new LoggingCallable();
        $factory = $this->newFactory();
        $factory->on('', $listener);
        $validator = $factory->create($rules);
        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertCount(0, $listener);
    }

    /**
     * @test
     */
    public function create_validator_with_custom_factory()
    {
        $factory = $this->newFactory();
        $custom = function (array $rules, string $ormClass = '') {
            return new DoesNotSupportRuleSetting($rules, $ormClass);
        };
        $factory->setCreateFactory($custom);

        $validator = $factory->create([]);
        $this->assertInstanceOf(DoesNotSupportRuleSetting::class, $validator);
    }

    /**
     * @test
     */
    public function get_registered_validator()
    {
        $factory = $this->newFactory();
        $factory->register(ValidatorFactoryTest_Address::class, function () {
            return new ValidatorFactoryTest_AddressValidator();
        });

        $validator = $factory->get(ValidatorFactoryTest_Address::class);
        $this->assertInstanceOf(
            ValidatorFactoryTest_AddressValidator::class,
            $validator
        );
    }

    /**
     * @test
     */
    public function get_registered_validator_by_class()
    {
        $factory = $this->newFactory();
        $check = null;
        $validator = new ValidatorFactoryTest_AddressValidator();

        $factory->register(
            ValidatorFactoryTest_Address::class,
            ValidatorFactoryTest_AddressValidator::class
        );
        $handler = function (string $ormClass) use ($validator, &$check) {
            $check = get_class($validator) == $ormClass;
            return $validator;
        };
        $factory->createObjectsBy($handler);
        $this->assertSame(
            $validator,
            $factory->get(ValidatorFactoryTest_Address::class)
        );
        $this->assertTrue($check);
    }

    /**
     * @test
     */
    public function get_without_handler_throws_Exception()
    {
        $factory = $this->newFactory();
        $this->expectException(ValidatorFabricationException::class);
        $factory->get(ValidatorFactoryTest_Address::class);
    }

    /**
     * @test
     */
    public function creating_wrong_validator_throws_exception()
    {
        $factory = $this->newFactory();

        $factory->register(ValidatorFactoryTest_Address::class, function () {
            return new stdClass();
        });

        $this->expectException(ValidatorFabricationException::class);
        $factory->get(ValidatorFactoryTest_Address::class);
    }

    /**
     * @test
     */
    public function registered_class_not_implementing_validator_throws_exception(
    )
    {
        $factory = $this->newFactory();

        $factory->register(
            ValidatorFactoryTest_Address::class,
            stdClass::class
        );

        $this->expectException(ValidatorFabricationException::class);
        $factory->get(ValidatorFactoryTest_Address::class);
    }

    /**
     * @test
     */
    public function register_validator_directly_throws_exception()
    {
        $factory = $this->newFactory(function () {
        });
        $this->expectException(InvalidArgumentException::class);
        $factory->register(
            stdClass::class,
            new ValidatorFactoryTest_AddressValidator()
        );
    }

    /**
     * @test
     */
    public function register_wrong_type_as_validator_throws_exception()
    {
        $factory = $this->newFactory(function () {
        });
        $this->expectException(InvalidArgumentException::class);
        $factory->register(stdClass::class, 33);
    }

    /**
     * @test
     */
    public function validate_validates_by_validator()
    {
        $object = new ValidatorFactoryTest_Address();
        $factory = $this->newFactory();
        try {
            $factory->validate(['login' => 'required'], [], $object);
            $this->fail('Validate did not throw an exception');
        } catch (ValidationException $e) {
            $this->assertSame([], $e->failures()['login']['required']);
        }
    }

    /**
     * @test
     */
    public function forwardValidatorEvent_forwards_event()
    {
        $factory = $this->newFactory();
        $logger = new LoggingCallable(function () {
        });
        $factory->on(stdClass::class, $logger);
        $validator = new Validator([], stdClass::class);
        $factory->forwardValidatorEvent($validator);
        $this->assertSame($validator, $logger->arg(0));
    }

    protected function newFactory(callable $fallbackFactory = null
    ): ValidatorFactory {
        return new ValidatorFactory($fallbackFactory);
    }
}


class DoesNotSupportRuleSetting extends Validator
{

    public function resource() : NamedObject
    {
        return new NamedObject(1,'foo','foo');
    }

    public function canMergeRules(): bool
    {
        return false;
    }


    protected function validateByBaseValidator(
        Validation $validation,
        array $input,
        array $baseRules,
        $ormObject = null,
        array $formats = []
    ): array {
        return $input;
    }


}

class ValidatorFactoryTest_Address
{
    //
}

class ValidatorFactoryTest_AddressValidator extends Validator
{
    protected $rules = [
        'street'        => 'required|min:5|max:58',
        'city'          => 'required|min:2|max:64',
        'house_number'  => 'string|min:2|max:16',
    ];

    public function ormClass(): string
    {
        return ValidatorFactoryTest_Address::class;
    }

}