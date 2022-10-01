<?php


namespace Koansu\Tests\Core;


use Koansu\Core\Contracts\SupportsCustomFactory;
use Koansu\Core\CustomFactoryTrait;
use Koansu\Core\Exceptions\SymbolNotFoundException;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\TestCase;


class CustomFactoryTraitTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(SupportsCustomFactory::class, $this->newObject());
    }

    /**
     * @test
     */
    public function it_creates_without_callable()
    {
        $object = $this->newObject()->make(CreateMe::class);
        $this->assertInstanceOf(CreateMe::class, $object);
    }

    /**
     * @test
     */
    public function it_creates_without_callable_and_parameters()
    {
        $object = $this->newObject()->make(CreateMeWithParameters::class, [[],new \stdClass]);
        $this->assertInstanceOf(CreateMeWithParameters::class, $object);
    }

    /**
     * @test
     **/
    public function createObject_with_not_existing_class_throws_exception()
    {
        $this->expectException(SymbolNotFoundException::class);
        $object = $this->newObject()->make('foo');
    }

    /**
     * @test
     **/
    public function it_creates_with_callable()
    {

        $creator = new LoggingCallable(function ($class, array $parameters=[]) {
            return new CreateMe;
        });

        $factory = $this->newObject();
        $factory->createObjectsBy($creator);

        $object = $factory->make(CreateMe::class);

        $this->assertInstanceOf(CreateMe::class, $object);
        $this->assertCount(1, $creator);
        $this->assertEquals(CreateMe::class, $creator->arg(0));
    }

    public function test_it_creates_with_callable_and_parameters()
    {

        $creator = new LoggingCallable(function ($class, array $parameters=[]) {
            return new CreateMe;
        });

        $factory = $this->newObject();
        $factory->createObjectsBy($creator);

        $object = $factory->make(CreateMe::class, ['a', 'b']);

        $this->assertInstanceOf(CreateMe::class, $object);
        $this->assertCount(1, $creator);
        $this->assertEquals(CreateMe::class, $creator->arg(0));
        $this->assertEquals(['a', 'b'], $creator->arg(1));
    }

    protected function newObject()
    {
        return new class implements SupportsCustomFactory {
            use CustomFactoryTrait;
            public function make(string $abstract, array $parameters=[])
            {
                return $this->createObject($abstract, $parameters);
            }
        };
    }
}

class CreateMe {}



class CreateMeWithParameters
{
    public function __construct(array $some, \stdClass $other)
    {

    }
}

class CustomFactorySupportObject implements SupportsCustomFactory
{
    use CustomFactoryTrait;

    public function make($class=null, array $parameters=[])
    {
        return $this->createObject($class, $parameters);
    }
}

class CustomFactorySupportWithFixedAbstract extends CustomFactorySupportObject {
    protected $factoryAbstract = CreateMe::class;
}
