<?php
/**
 *  * Created by mtils on 17.12.17 at 11:13.
 **/

namespace Koansu\Tests\Core;


use ArrayIterator;
use Countable;
use Koansu\Core\DataStructures\Sequence;
use Koansu\Core\Type;
use Koansu\Core\Url;
use Koansu\Tests\TestCase;
use Traversable;
use TypeError;


class TypeTest extends TestCase
{

    /**
     * @test
     */
    public function is_returns_true_on_null_if_nullable()
    {
        $this->assertTrue(Type::is(null, 'string', true));
        $this->assertFalse(Type::is(null, 'string'));
    }

    /**
     * @test
     */
    public function is_returns_true_if_all_types_matches()
    {
        $this->assertTrue(Type::is(15, ['numeric', 'int']));
        $this->assertFalse(Type::is(15, ['numeric', 'float']));
    }

    /**
     * @test
     */
    public function is_returns_true_if_traversable()
    {
        $this->assertTrue(Type::is(new ArrayIterator(), Traversable::class));
        $this->assertTrue(Type::is([], Traversable::class));
    }

    /**
     * @test
     */
    public function is_returns_true_if_countable()
    {
        $this->assertTrue(Type::is(new ArrayIterator(), Countable::class));
        $this->assertTrue(Type::is([], Countable::class));
    }

    /**
     * @test
     */
    public function toBool_returns_right_values()
    {
        $this->assertTrue(Type::toBool('') === false);
        $list = new Sequence([1,2,3]);
        $this->assertTrue(Type::toBool($list) === true);
        $this->assertTrue(Type::toBool(new Sequence()) === false);
        $this->assertTrue(Type::toBool(' ') === false);
        $this->assertTrue(Type::toBool('0') === false);
        $this->assertTrue(Type::toBool(false) === false);
        $this->assertTrue(Type::toBool('false') === false);
        $this->assertTrue(Type::toBool('true') === true);
        $this->assertTrue(Type::toBool(new Url()) === false);

    }

    /**
     * @test
     */
    public function toArray_throws_exception_when_not_castable()
    {
        $this->expectException(TypeError::class);
        /** @noinspection PhpParamsInspection */
        Type::toArray(0.127);
    }

    /**
     * @test
     */
    public function force_throws_exception_if_type_does_not_match()
    {
        $this->expectException(TypeError::class);
        Type::force(145, 'string');
    }

    /**
     * @test
     */
    public function test_traits_returns_directly_used_traits()
    {
        // Test not nested traits
        $this->assertEquals([
            TypeTest_Trait1::class => TypeTest_Trait1::class
        ], Type::traits(TypeTest_TraitTest1::class));

        // Test nested traits
        $this->assertEquals([
            TypeTest_Trait1::class => TypeTest_Trait1::class,
            TypeTest_Trait2::class => TypeTest_Trait2::class,
        ], Type::traits(TypeTest_TraitTest12::class));

        $this->assertEquals([], Type::traits(TypeTest_TraitTest1_extended::class));
    }

    /**
     * @test
     */
    public function traits_returns_traits_from_parent_classes()
    {
        // Test one trait of parent class
        $this->assertEquals([
            TypeTest_Trait1::class => TypeTest_Trait1::class
        ], Type::traits(TypeTest_TraitTest1_extended::class, true));

        $this->assertEquals([
            TypeTest_Trait1::class => TypeTest_Trait1::class
        ], Type::traits(TypeTest_TraitTest1_extended2::class, true));

    }

    /**
     * @test
     */
    public function traits_returns_traits_used_by_traits()
    {
        // Test one trait of parent class
        $this->assertEquals([
            TypeTest_Trait1::class => TypeTest_Trait1::class,
            TypeTest_Trait2::class => TypeTest_Trait2::class,
            TypeTest_SubTrait1::class => TypeTest_SubTrait1::class
        ], Type::traits(TypeTest_TraitTest12::class, true));

        $this->assertEquals([
            TypeTest_Trait3::class => TypeTest_Trait3::class,
            TypeTest_SubTrait2::class => TypeTest_SubTrait2::class,
            TypeTest_SubTrait_of_SubTrait::class => TypeTest_SubTrait_of_SubTrait::class
        ], Type::traits(TypeTest_TraitTest3::class, true));

        $this->assertEquals([
            TypeTest_SubTrait1::class => TypeTest_SubTrait1::class,
            TypeTest_SubTrait2::class => TypeTest_SubTrait2::class,
            TypeTest_SubTrait_of_SubTrait::class => TypeTest_SubTrait_of_SubTrait::class,
            TypeTest_Trait4::class => TypeTest_Trait4::class
        ], Type::traits(TypeTest_TraitTest4::class, true));

    }
    /**
     * @test
     */
    public function classInFile_returns_class_with_namespace()
    {
        $this->assertEquals(self::class, Type::classInFile(__FILE__));
    }

    /**
     * @test
     */
    public function classInFile_returns_class()
    {
        $file = $this->dirOfTests('database/migrations/2014_05_26_092001_create_users_table.php');
        $this->assertEquals('CreateUsersTable', Type::classInFile($file));
    }

    /**
     * @test
     */
    public function classInFile_returns_anonymous_class()
    {
        $file = $this->dirOfTests('data/reflection/anonymous-class-returned.php');
        $this->assertEquals(Type::ANONYMOUS_CLASS, Type::classInFile($file));
    }

    /**
     * @test
     */
    public function classInFile_returns_anonymous_class_after_other_instantiation()
    {
        $file = $this->dirOfTests('data/reflection/anonymous-class-after-instantiate-returned.php');
        $this->assertEquals(Type::ANONYMOUS_CLASS, Type::classInFile($file));
    }

    /**
     * @test
     */
    public function classInFile_returns_real_class_after_other_instantiation()
    {
        $file = $this->dirOfTests('data/reflection/class-after-anonymous-returned.php');
        $this->assertEquals('AfterAnonymous', Type::classInFile($file));
    }

    /**
     * @test
     */
    public function classInFile_returns_real_class_after_class_in_function()
    {
        $file = $this->dirOfTests('data/reflection/class-after-function-with-class.php');
        $this->assertEquals('AfterFunctionWithClass', Type::classInFile($file));
    }

    /**
     * @test
     */
    public function closest_returns_direct_match()
    {
        $this->assertSame(TypeTest_Base::class, Type::closest([TypeTest_Base::class], TypeTest_Base::class));
    }

    /**
     * @test
     */
    public function closest_returns_match_of_parent_class()
    {
        $this->assertSame(TypeTest_Base::class, Type::closest([TypeTest_Base::class], TypeTest_ExtendedBase::class));
    }

    /**
     * @test
     */
    public function closest_returns_closest_class_by_hierarchy()
    {
        $candidates = [
            TypeTest_Base::class, TypeTest_ExtendedBase::class
        ];
        $this->assertSame(TypeTest_Base::class, Type::closest($candidates, TypeTest_Base::class));
        $this->assertSame(TypeTest_ExtendedBase::class, Type::closest($candidates, TypeTest_ExtendedBase::class));
        $this->assertSame(TypeTest_ExtendedBase::class, Type::closest($candidates, TypeTest_EvenMoreExtendedBase::class));
        $this->assertSame('', Type::closest($candidates, self::class));

        $candidates2 = [
            TypeTest_Base::class, TypeTest_EvenMoreExtendedBase::class
        ];
        $this->assertSame(TypeTest_Base::class, Type::closest($candidates2, TypeTest_Base::class));
        $this->assertSame(TypeTest_Base::class, Type::closest($candidates2, TypeTest_ExtendedBase::class));
        $this->assertSame(TypeTest_EvenMoreExtendedBase::class, Type::closest($candidates2, TypeTest_EvenMoreExtendedBase::class));


    }
}

trait TypeTest_SubTrait_of_SubTrait
{

}

trait TypeTest_SubTrait2
{
    use TypeTest_SubTrait_of_SubTrait;
}

trait TypeTest_SubTrait1
{

}

trait TypeTest_Trait1
{

}

trait TypeTest_Trait2
{
    use TypeTest_SubTrait1;
}

trait TypeTest_Trait3
{
    use TypeTest_SubTrait2;
}

trait TypeTest_Trait4
{
    use TypeTest_SubTrait1;
    use TypeTest_SubTrait2;
}

class TypeTest_TraitTest1
{
    use TypeTest_Trait1;
}

class TypeTest_TraitTest1_extended extends TypeTest_TraitTest1
{
    //
}

class TypeTest_TraitTest12
{
    use TypeTest_Trait1;
    use TypeTest_Trait2;

}

class TypeTest_TraitTest3
{
    use TypeTest_Trait3;
}

class TypeTest_TraitTest3_extended extends TypeTest_TraitTest3
{
    //
}

class TypeTest_TraitTest4
{
    use TypeTest_Trait4;
}

class TypeTest_TraitTest1_extended2 extends TypeTest_TraitTest1_extended
{
    //
}

class TypeTest_Base{}

class TypeTest_ExtendedBase extends TypeTest_Base {}

class TypeTest_EvenMoreExtendedBase extends TypeTest_ExtendedBase {}

class TypeTest_Extension
{
    public $name = 'a';

    public function __construct($name='a')
    {
        $this->name = $name;
    }

    public function __invoke()
    {
    }
}