<?php
/**
 *  * Created by mtils on 05.02.2023 at 11:50.
 **/

namespace Koansu\Validation;

use Closure;
use InvalidArgumentException;
use Koansu\Core\DataStructures\ByTypeContainer;
use Koansu\Core\Contracts\Subscribable;
use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Validation\Contracts\Validator as ValidatorContract;
use Koansu\Validation\Contracts\ValidatorFactory as ValidatorFactoryContract;
use Koansu\Validation\Exceptions\ValidatorFabricationException;
use Koansu\Validation\Matcher;
use Koansu\Core\SubscribableTrait;
use Koansu\Core\CustomFactoryTrait;
use ReflectionException;

use function get_class;
use function is_callable;
use function is_object;
use function is_string;
use function spl_object_hash;

class ValidatorFactory implements ValidatorFactoryContract, SupportsCustomFactory, Subscribable
{
    use CustomFactoryTrait {
        createWithoutFactory as traitCreateWithoutFactory;
    }
    use SubscribableTrait {
        on as traitOn;
    }

    /**
     * @var ByTypeContainer
     */
    protected $factories;

    /**
     * @var callable
     */
    protected $createFactory;

    /**
     * @var ValidatorContract|null
     */
    protected $lastPublishedValidator;

    public function __construct(callable $factory = null)
    {
        $this->factories = new ByTypeContainer();
        if ($factory) {
            $this->createObjectsBy($factory);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param array $rules
     * @param string $ormClass
     * @return ValidatorContract
     */
    public function create(array $rules, string $ormClass = ''): ValidatorContract
    {
        $factory = $this->getCreateFactory();
        if (!$ormClass) {
            return $factory($rules, $ormClass);
        }
        $validator = $factory($rules, $ormClass);
        $this->publish($ormClass, $validator);
        return $validator;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $ormClass
     * @return ValidatorContract
     * @throws ValidatorFabricationException
     */
    public function get(string $ormClass): ValidatorContract
    {
        $validator = $this->validator($ormClass);
        $this->publish($ormClass, $validator);
        return $validator;
    }

    /**
     * {@inheritDoc}
     *
     * @param array $rules
     * @param array $input
     * @param object|null $ormObject
     * @param array $formats
     *
     * @return array
     */
    public function validate(array $rules, array $input, object $ormObject = null, array $formats = []): array
    {
        $ormClass = is_object($ormObject) ? get_class($ormObject) : '';
        $validator = $this->create($rules, $ormClass);
        return $validator->validate($input, $ormObject, $formats);
    }

    /**
     * Register a validator for $ormClass.
     *
     * @param string $ormClass
     * @param string|callable $validatorClassOrFactory
     * @return void
     * @noinspection PhpMissingParamTypeInspection
     */
    public function register(string $ormClass, $validatorClassOrFactory) : void
    {
        $this->factories[$ormClass] = $this->checkAndReturn($validatorClassOrFactory);
    }

    /**
     * Get the factory that is used inside create.
     *
     * @return callable
     */
    public function getCreateFactory() : callable
    {
        if (!$this->createFactory) {
            $this->createFactory = $this->makeCreateFactory();
        }
        return $this->createFactory;
    }

    /**
     * Set the factory that is used in create.
     *
     * @param callable $factory
     * @return $this
     */
    public function setCreateFactory(callable $factory) : ValidatorFactory
    {
        $this->createFactory = $factory;
        return $this;
    }

    /**
     * Register a listener that gets informed if a validator for ormClass $event
     * is returned (by create or get).
     *
     * @param string $event The orm class
     * @param callable $listener
     *
     * @return ValidatorFactory
     */
    public function on($event, callable $listener) : ValidatorFactory
    {
        $this->traitOn($event, $listener);
        return $this;
    }

    /**
     * Use this method to forward resolving events from your container so that
     * the listeners will also be called.
     *
     * @param ValidatorContract $validator
     * @return void
     */
    public function forwardValidatorEvent(ValidatorContract $validator) : void
    {

        if (!$ormClass = $validator->ormClass()) {
            return;
        }
        $this->publish($ormClass, $validator);
    }

    /**
     * Make the default handler for creating fresh validators in self::create()
     *
     * @return Closure
     */
    protected function makeCreateFactory() : Closure
    {
        return function (array $rules, string $ormClass='') {
            return $this->createObject(Validator::class, [
                'rules'         => $rules,
                'ormClass'      => $ormClass,
                'baseValidator' => $this->createObject(MatcherBaseValidator::class)
            ]);
        };
    }

    /**
     * @param string $ormClass
     * @return Validator
     */
    protected function validator(string $ormClass) : ValidatorContract
    {
        if (!$factoryOrClass = $this->factories->forInstanceOf($ormClass)) {
            throw new ValidatorFabricationException("No handler registered for class '$ormClass'", ValidatorFabricationException::NO_FACTORY_FOR_ORM_CLASS);
        }

        try {
            $validator = is_callable($factoryOrClass) ? $factoryOrClass($ormClass) : $this->createObject($factoryOrClass);
        } catch (ReflectionException $e) {
            throw new ValidatorFabricationException(
                "ReflectionException while trying to create validator for '$ormClass'",
                ValidatorFabricationException::UNRESOLVABLE_BY_FACTORY,
                $e
            );
        }


        if ($validator instanceof ValidatorContract) {
            return $validator;
        }

        if (is_string($factoryOrClass)) {
            throw new ValidatorFabricationException(
                "The registered class or binding $factoryOrClass must implement " . ValidatorContract::class,
                ValidatorFabricationException::WRONG_TYPE_REGISTERED
            );
        }

        $type = is_object($factoryOrClass) ? get_class($factoryOrClass) : 'callable';

        throw new ValidatorFabricationException(
            "The registered factory of type '$type' did not return a " . ValidatorContract::class,
            ValidatorFabricationException::FACTORY_RETURNED_WRONG_TYPE
        );

    }

    /**
     * Check the factory before adding it.
     *
     * @param $validatorClassOrFactory
     * @return string|callable
     * @throws InvalidArgumentException
     */
    protected function checkAndReturn($validatorClassOrFactory)
    {
        if ($validatorClassOrFactory instanceof Validator) {
            throw new InvalidArgumentException("It is not allowed to register validators directly to avoid loading masses of classes on boot");
        }

        // Class or callable. Avoid checking for class existence to not load
        // files for nothing
        if (!is_string($validatorClassOrFactory) && !is_callable($validatorClassOrFactory)) {
            throw new InvalidArgumentException('Factory has to be a class name or a callable.');
        }

        return $validatorClassOrFactory;
    }

    /**
     * Reimplemented over trait to accept class names.
     *
     * @param string|object $event
     * @return void
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function checkEvent($event) : void
    {
        // Accept everything
    }

    /**
     * Overwritten in case of missing factory.
     *
     * @param string $abstract
     * @param array $parameters
     * @return MatcherBaseValidator|object
     * @throws ReflectionException
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function createWithoutFactory($abstract, array $parameters = [])
    {
        if ($abstract === MatcherBaseValidator::class) {
            /** @noinspection PhpParamsInspection */
            return new MatcherBaseValidator($this->createObject(Matcher::class));
        }
        return $this->traitCreateWithoutFactory($abstract, $parameters);
    }

    /**
     * Publish the validator by subscribable trait.
     *
     * @param string $ormClass
     * @param ValidatorContract $validator
     * @return void
     */
    protected function publish(string $ormClass, ValidatorContract $validator) : void
    {
        if ($this->isLastPublishedValidator($validator)) {
            return;
        }
        $this->lastPublishedValidator = $validator;
        $this->callOnListeners($ormClass, [$validator]);
    }

    /**
     * Check if the passed validator is the last published. This is needed to
     * avoid double events for validators.
     *
     * @param ValidatorContract $validator
     * @return bool
     */
    protected function isLastPublishedValidator(ValidatorContract $validator) : bool
    {
        if (!$this->lastPublishedValidator) {
            return false;
        }
        return spl_object_hash($validator) == spl_object_hash($this->lastPublishedValidator);
    }
}
