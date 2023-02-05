<?php
/**
 *  * Created by mtils on 05.02.2023 at 12:26.
 **/

namespace Koansu\Validation\Skeleton;

use Koansu\Skeleton\AppExtension;
use Koansu\Validation\Contracts\Validator as ValidatorContract;
use Koansu\Validation\Validator;
use Koansu\Validation\ValidatorFactory;
use Koansu\Validation\Contracts\ValidatorFactory as ValidatorFactoryContract;

class ValidationExtension extends AppExtension
{
    public function bind(): void
    {
        $this->app->bind(ValidatorContract::class, Validator::class);
        $this->app->share(ValidatorFactoryContract::class, function () {
            return $this->app->create(ValidatorFactory::class, ['factory' => $this->app]);
        });
        $this->forwardDirectResolvingEventsToFactory();
    }

    /**
     * Forward resolving events of validators not created by the factory.
     *
     * @return void
     */
    protected function forwardDirectResolvingEventsToFactory() : void
    {
        $this->app->on(ValidatorContract::class, function (ValidatorContract $validator) {
            $factory = $this->app->get(ValidatorFactoryContract::class);
            if (!$factory instanceof ValidatorFactory) {
                return;
            }
            $factory->forwardValidatorEvent($validator);
        });
    }
}