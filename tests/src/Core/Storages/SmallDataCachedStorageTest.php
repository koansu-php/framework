<?php
/**
 *  * Created by mtils on 12.09.18 at 12:31.
 **/

namespace Koansu\Tests\Core\Storages;


use IteratorAggregate;
use Koansu\Core\Contracts\Arrayable;
use Koansu\Core\Contracts\Storage;
use Koansu\Core\Exceptions\KeyNotFoundException;
use Koansu\Core\Storages\ArrayStorage;
use Koansu\Core\Storages\SmallDataCachedStorage;
use Koansu\Tests\TestCase;


class SmallDataCachedStorageTest extends TestCase
{


    /**
     * @test
     */
    public function implements_interface()
    {
        $storage = $this->newStorage();
        $this->assertInstanceOf(Storage::class, $storage);
        $this->assertInstanceOf(Arrayable::class, $storage);
        $this->assertInstanceOf(IteratorAggregate::class, $storage);
    }

    /**
     * @test
     */
    public function storageType_is_memory()
    {
        $this->assertEquals(Storage::UTILITY, $this->newStorage()->storageType());
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
    public function offsetGet_throws_exception_if_key_not_found()
    {
        $storage = $this->newStorage();
        $this->expectException(KeyNotFoundException::class);
        $storage['foo'];
    }

    /**
     * @test
     */
    public function offsetSet_deletes_cache()
    {
        $data = ['test' => 'one','test2' => 'two'];
        $array = new ArrayStorage($data);
        $storage = $this->newStorage($array);
        $this->assertEquals($data['test'], $storage['test']);
        $storage['test'] = 'three';
        $this->assertEquals('three', $storage['test']);
    }

    /**
     * @test
     */
    public function offsetUnset_deletes_cache()
    {
        $data = ['test' => 'one', 'test2' => 'two'];
        $array = new ArrayStorage($data);
        $storage = $this->newStorage($array);
        $this->assertEquals($data['test'], $storage['test']);
        unset($storage['test']);
        $this->assertFalse(isset($storage['test']));
    }

    /**
     * @test
     */
    public function clear_purges_storage()
    {
        $data = ['test' => 'one'];
        $storage = $this->newStorage(new ArrayStorage($data));
        $this->assertEquals($data['test'], $storage['test']);
        $storage->clear();
        $this->assertFalse(isset($storage['test']));
    }

    /**
     * @param Storage|null $storage
     *
     * @return SmallDataCachedStorage
     */
    protected function newStorage(Storage $storage=null) : SmallDataCachedStorage
    {
        $storage = $storage ?: new ArrayStorage();
        return new SmallDataCachedStorage($storage);
    }
}