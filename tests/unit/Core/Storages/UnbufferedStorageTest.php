<?php

namespace Koansu\Tests\Core\Storages;



use Koansu\Core\Contracts\Storage as StorageContract;
use Koansu\Core\Storages\UnbufferedProxyStorage;
use Koansu\Tests\TestCase;
use LogicException;

class UnbufferedStorageTest extends TestCase
{

    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(StorageContract::class, $this->newProxy());
    }

    /**
     * @test
     */
    public function instantiating_fails_with_already_unbuffered_storage()
    {
        $unbufferedStorage = $this->mock(StorageContract::class);
        $unbufferedStorage->shouldReceive('isBuffered')->andReturn(false);
        $this->expectException(LogicException::class);
        $this->newProxy($unbufferedStorage);
    }

    /**
     * @test
     */
    public function is_unbuffered()
    {
        $this->assertFalse($this->newProxy()->isBuffered());
    }

    /**
     * @test
     */
    public function forwards_to_offsetExists()
    {
        $storage = $this->mockStorage();
        $proxy = $this->newProxy($storage);

        $storage->shouldReceive('offsetExists')
                ->with('foo')
                ->once()
                ->andReturn(true);

        $this->assertTrue(isset($proxy['foo']));
    }

    /**
     * @test
     */
    public function forwards_to_offsetGet()
    {
        $storage = $this->mockStorage();
        $proxy = $this->newProxy($storage);

        $storage->shouldReceive('offsetGet')
                ->with('foo')
                ->once()
                ->andReturn('bar');

        $this->assertEquals('bar', $proxy['foo']);
    }

    /**
     * @test
     */
    public function forwards_to_offsetSet_and_persist()
    {
        $storage = $this->mockStorage();
        $proxy = $this->newProxy($storage);

        $storage->shouldReceive('offsetSet')
                ->with('foo', 'bar')
                ->once();

        $storage->shouldReceive('persist')
                ->andReturn(true)
                ->once();
        $proxy->offsetSet('foo', 'bar');
    }

    /**
     * @test
     */
    public function offsetUnset_forwards_to_clear()
    {
        $storage = $this->mockStorage();
        $proxy = $this->newProxy($storage);

        $storage->shouldReceive('offsetUnset')
                ->with('foo')
                ->once();
        $storage->shouldReceive('persist')->once();


        $proxy->offsetUnset('foo');
    }

    /**
     * @test
     */
    public function clear_forwards_to_clear()
    {
        $storage = $this->mockStorage();
        $proxy = $this->newProxy($storage);

        $storage->shouldReceive('clear')
                ->with(null)
                ->once();
        $storage->shouldReceive('persist')->once();

        $proxy->clear();
    }

    /**
     * @test
     */
    public function storageType_returns_utility()
    {
        $this->assertEquals(StorageContract::UTILITY, $this->newProxy()->storageType());
    }

    protected function newProxy(StorageContract $storage=null) : UnbufferedProxyStorage
    {
        return new UnbufferedProxyStorage($storage ?: $this->mockStorage());
    }

    protected function mockStorage()
    {
        $mock = $this->mock(StorageContract::class);
        $mock->shouldReceive('isBuffered')->andReturn(true);
        return $mock;
    }
}
