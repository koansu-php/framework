<?php
/**
 *  * Created by mtils on 18.12.2022 at 09:22.
 **/

namespace Koansu\Tests\Filesystem\Storages;

use Koansu\Core\Exceptions\DataIntegrityException;
use Koansu\Core\Serializer;
use Koansu\Core\Url;
use Koansu\Filesystem\Contracts\Filesystem;
use Koansu\Filesystem\LocalFilesystem;
use Koansu\Tests\FilesystemMethods;
use Koansu\Tests\TestCase;
use Koansu\Core\Contracts\Storage;
use Koansu\Filesystem\Storages\SingleFileStorage;
use Koansu\Core\Contracts\Serializer as SerializerContract;

class SingleFileStorageTest extends TestCase
{
    use FilesystemMethods;

    /**
     * @test
     */
    public function implements_interface()
    {
        $this->assertInstanceOf(Storage::class, $this->newStorage());
    }

    /**
     * @test
     */
    public function isBuffered_returns_true()
    {
        $this->assertTrue($this->newStorage()->isBuffered());
    }

    /**
     * @test
     */
    public function getUrl_returns_set_url()
    {
        $storage = $this->newStorage();
        $url = new Url('/home/michael');
        $this->assertSame($storage, $storage->setUrl($url));
        $this->assertSame($url, $storage->getUrl());
    }

    /**
     * @test
     */
    public function storageType_returns_type()
    {
        $this->assertEquals('filesystem', $this->newStorage()->storageType());
    }

    /**
     * @test
     */
    public function persist_and_return_value()
    {
        $storage = $this->newStorage();
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();
        unset($storage);

        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);
    }

    /**
     * @test
     */
    public function auto_persist_on_offsetSet_if_write_on_change_option_is_set()
    {
        $storage = $this->newStorage();
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();

        unset($storage);

        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);
    }

    /**
     * @test
     */
    public function auto_persist_on_offsetUnset_if_write_on_change_option_is_set()
    {
        $storage = $this->newStorage();
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();

        unset($storage);

        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);

        unset($storage2['a']);
        $storage2->persist();
        unset($storage2);


        $storage3 = $this->newStorage()->setUrl($url);
        $this->assertEquals('bar', $storage3['foo']);
        $this->assertFalse(isset($storage3['a']));
    }

    /**
     * @test
     */
    public function persist_and_return_value_without_checksum()
    {
        $storage = $this->newStorage(null, null, false);
        $storage->setOption('checksum_method', '');
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();
        unset($storage);

        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);
    }

    /**
     * @test
     */
    public function persist_throws_exception_if_checksum_failed()
    {
        $storage = $this->newStorage(null, null, false);

        $storage->checkChecksumBy(function ($method, $data) {
            return substr(md5(microtime()), rand(0, 26), 5);
        });

        $this->expectException(DataIntegrityException::class);
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();
        unset($storage);

        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);
    }

    /**
     * @test
     */
    public function clear_empties_storage()
    {
        $storage = $this->newStorage(null, null, false);
        $fs = new LocalFilesystem();

        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();
        unset($storage);

        $this->assertTrue($fs->exists($url));
        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);
        $storage2->clear();

        $this->assertFalse(isset($storage['bar']));
        $this->assertFalse(isset($storage['a']));
        $storage2->persist();

        $this->assertFalse($fs->exists($url));
    }

    /**
     * @test
     */
    public function clear_with_passed_keys()
    {
        $storage = $this->newStorage(null, null, false);
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $storage['foo'] = 'bar';
        $storage['a'] = 'b';
        $storage->persist();
        unset($storage);

        $storage2 = $this->newStorage();
        $storage2->setUrl($url);
        $this->assertEquals('bar', $storage2['foo']);
        $this->assertEquals('b', $storage2['a']);

        $storage2->clear([]);
        $storage2->clear(['bar']);

        $this->assertFalse(isset($storage2['bar']));
        $this->assertTrue(isset($storage2['a']));
    }

    /**
     * @test
     */
    public function isset_triggers_load()
    {
        $storage = $this->newStorage(null, null, false);
        $url = new Url($this->tempFileName());
        $storage->setUrl($url);
        $this->assertFalse(isset($storage['foo']));
    }

    protected function newStorage(Filesystem $files=null, SerializerContract $serializer=null, $autoPersist=true) : SingleFileStorage
    {
        $files = $files ?: $this->newFilesystem();
        $serializer = $serializer ?: $this->newSerializer();
        $storage = new SingleFileStorage($files, $serializer);

        if ($autoPersist) {
//             $storage->onAfter('offsetSet', function () use ($storage) { $storage->persist(); });
//             $storage->onAfter('offsetUnset', function () use ($storage) { $storage->persist(); });
        }

        return $storage;
    }

    protected function newSerializer() : SerializerContract
    {
        return new Serializer();
    }
}
