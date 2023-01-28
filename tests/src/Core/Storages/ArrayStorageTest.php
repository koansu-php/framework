<?php
/**
 *  * Created by mtils on 12.09.18 at 12:31.
 **/

namespace Koansu\Tests\Core\Storages;


use ArrayAccess;
use IteratorAggregate;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Storage;
use Koansu\Core\Storages\ArrayStorage;
use Koansu\Tests\TestCase;

class ArrayStorageTest extends TestCase
{
    /**
     * @test
     */
    public function implements_interface()
    {
        $storage = $this->newStorage();
        $this->assertInstanceOf(Storage::class, $storage);
        $this->assertInstanceOf(IteratorAggregate::class, $storage);
        $this->assertInstanceOf(Arrayable::class, $storage);
    }

    /**
     * @test
     */
    public function storageType_is_memory()
    {
        $this->assertEquals(Storage::MEMORY, $this->newStorage()->storageType());
    }

    /**
     * @test
     */
    public function persist_returns_true()
    {
        $this->assertTrue($this->newStorage()->persist());
    }

    /**
     * @test
     */
    public function isBuffered_returns_true()
    {
        $this->assertFalse($this->newStorage()->isBuffered());
    }

    /**
     * @test
     */
    public function clear_clears_storage()
    {
        $data = ['test' => 'one'];
        $storage = $this->newStorage($data);
        $this->assertEquals($data['test'],$storage['test']);
        $storage->clear();
        $this->assertFalse(isset($storage['test']));
    }

    /**
     * @test
     */
    public function offsetSet_sets_value()
    {
        $data = ['test' => 'one'];
        $storage = $this->newStorage($data);
        $this->assertEquals($data['test'],$storage['test']);
        $storage['foo'] = 'bar';
        $this->assertEquals('bar', $storage['foo']);
        unset($storage['foo']);
        $this->assertFalse(isset($storage['foo']));
    }

    /**
     * @test
     */
    public function clear_removes_keys()
    {
        $data = ['test' => 'one'];
        $storage = $this->newStorage($data);
        $this->assertEquals($data['test'],$storage['test']);
        $storage->clear(['test']);
        $this->assertFalse(isset($storage['test']));
    }

    /**
     * @test
     */
    public function clear_removes_nothing_if_empty_keys_passed()
    {
        $data = ['test' => 'one'];
        $storage = $this->newStorage($data);
        $this->assertEquals($data['test'],$storage['test']);
        $storage->clear([]);
        $this->assertTrue(isset($storage['test']));
    }

    /**
     * @param array $data (optional)
     *
     * @return ArrayStorage
     */
    protected function newStorage(array $data=[]) : ArrayStorage
    {
        return new ArrayStorage($data);
    }
}