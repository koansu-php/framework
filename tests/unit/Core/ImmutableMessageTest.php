<?php
/**
 *  * Created by mtils on 25.12.2021 at 07:33.
 **/

namespace Koansu\Tests\Core;

use BadMethodCallException;
use Koansu\Core\ImmutableMessage;
use Koansu\Core\Message;
use Koansu\Tests\TestCase;

class ImmutableMessageTest extends TestCase
{

    /**
     * @test
     */
    public function it_inherits_message()
    {
        $this->assertInstanceOf(Message::class, $this->message());
    }

    /**
     * @test
     */
    public function offsetSet_throws_exception()
    {
        $attributes = [
            'foo' => 'bar',
            'test' => ['a','b'],
            'activated' => true
        ];

        $message = $this->message($attributes);

        $this->expectException(BadMethodCallException::class);
        $message['foo'] = 'baz';

    }

    /**
     * @test
     */
    public function offsetUnset_throws_exception()
    {
        $attributes = [
            'foo' => 'bar',
            'test' => ['a','b'],
            'activated' => true
        ];

        $message = $this->message($attributes);

        $this->expectException(BadMethodCallException::class);
        unset($message['foo']);

    }

    /**
     * @test
     */
    public function with_returns_changed_instance()
    {
        $attributes = [
            'foo' => 'bar',
            'test' => ['a','b'],
            'activated' => true
        ];

        $message = $this->message($attributes);
        $fork = $message->with('foo', 'baz');
        $this->assertNotSame($message, $fork);
        $this->assertSame($message, $fork->previous);
        $this->assertSame($fork, $message->next);

        $this->assertEquals('bar', $message['foo']);
        $this->assertEquals('baz', $fork['foo']);
    }

    /**
     * @test
     */
    public function without_returns_changed_instance()
    {
        $attributes = [
            'foo' => 'bar',
            'test' => ['a','b'],
            'activated' => true
        ];

        $message = $this->message($attributes);
        $fork = $message->without('foo');
        $this->assertNotSame($message, $fork);

        $this->assertEquals('bar', $message['foo']);
        $this->assertFalse(isset($fork['foo']));
    }

    /**
     * @test
     */
    public function construct_applies_all_attributes()
    {
        $attributes = [
            'type' => Message::TYPE_INPUT,
            'transport' => Message::TRANSPORT_NETWORK,
            'custom'    => ['foo' => 'bar'],
            'envelope'  => ['Content-Type' =>  'application/json'],
            'payload'   => 'blob'
        ];

        $message = $this->message(
            $attributes['payload'],
            $attributes['envelope'],
            $attributes['type'],
            $attributes['transport']
        )->withCustom($attributes['custom']);

        $this->assertEquals($attributes['type'], $message->type);
        $this->assertEquals($attributes['transport'], $message->transport);
        $this->assertEquals($attributes['custom'], $message->custom);
        $this->assertEquals($attributes['envelope'], $message->envelope);
        $this->assertEquals($attributes['payload'], $message->payload);
    }

    /**
     * @test
     */
    public function withType_creates_new_instance_with_changed_type()
    {
        $attributes = [
            'type' => Message::TYPE_INPUT,
            'transport' => Message::TRANSPORT_NETWORK,
            'envelope'  => ['Content-Type' =>  'application/json'],
            'payload'   => 'blob'
        ];

        $message = $this->message(
            $attributes['payload'],
            $attributes['envelope'],
            $attributes['type'],
            $attributes['transport']
        );

        $fork = $message->withType(Message::TYPE_OUTPUT);
        $this->assertNotSame($message, $fork);
        $this->assertEquals(Message::TYPE_OUTPUT, $fork->type);
    }

    /**
     * @test
     */
    public function withTransport_creates_new_instance_with_changed_transport()
    {
        $attributes = [
            'type' => Message::TYPE_INPUT,
            'transport' => Message::TRANSPORT_NETWORK,
            'envelope'  => ['Content-Type' =>  'application/json'],
            'payload'   => 'blob'
        ];

        $message = $this->message(
            $attributes['payload'],
            $attributes['envelope'],
            $attributes['type'],
            $attributes['transport']
        );

        $fork = $message->withTransport(Message::TRANSPORT_IPC);
        $this->assertNotSame($message, $fork);
        $this->assertEquals(Message::TRANSPORT_IPC, $fork->transport);
    }

    /**
     * @test
     */
    public function withEnvelope_creates_new_instance_with_changed_envelope()
    {
        $attributes = [
            'type' => Message::TYPE_INPUT,
            'transport' => Message::TRANSPORT_NETWORK,
            'envelope'  => ['Content-Type' =>  'application/json'],
            'payload'   => 'blob'
        ];

        $newEnvelope = ['Content-Type' => 'text/html'];

        $message = $this->message(
            $attributes['payload'],
            $attributes['envelope'],
            $attributes['type'],
            $attributes['transport']
        );

        $fork = $message->withEnvelope($newEnvelope);
        $this->assertNotSame($message, $fork);
        $this->assertEquals($newEnvelope, $fork->envelope);
    }

    /**
     * @test
     */
    public function withPayload_creates_new_instance_with_changed_payload()
    {

        $attributes = [
            'type' => Message::TYPE_INPUT,
            'transport' => Message::TRANSPORT_NETWORK,
            'envelope'  => ['Content-Type' =>  'application/json'],
            'payload'   => 'blob'
        ];

        $message = $this->message(
            $attributes['payload'],
            $attributes['envelope'],
            $attributes['type'],
            $attributes['transport']
        );

        $fork = $message->withPayload('foo');
        $this->assertNotSame($message, $fork);
        $this->assertEquals('foo', $fork->payload);
    }

    protected function message(...$args) : ImmutableMessage
    {
        return new ImmutableMessage(...$args);
    }
}