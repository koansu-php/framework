<?php
/**
 *  * Created by mtils on 28.01.2023 at 11:28.
 **/

namespace Koansu\Database\Illuminate;

use Illuminate\Contracts\Events\Dispatcher;
use Koansu\Core\Contracts\Subscribable;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\SubscribableTrait;

use function array_unshift;
use function call_user_func;
use function is_callable;
use function is_object;

class DetachedDispatcher implements Dispatcher, Subscribable
{
    use SubscribableTrait;

    /**
     * {@inheritdoc}
     *
     * @param  string|array  $events
     * @param  mixed  $listener
     * @param  int  $priority
     * @return void
     */
    public function listen($events, $listener=null) : void
    {
        if (!is_callable($listener)) {
            throw new ImplementationException('This dispatcher only supports callable listeners');
        }
        $this->on($events, $listener);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName) : bool
    {
        return (bool)$this->getOnListeners($eventName);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function push($event, $payload = []) : void
    {
        $this->listen($event.'_pushed', function () use ($event, $payload) {
            $this->dispatch($event, $payload);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @param  object|string  $subscriber
     * @return void
     */
    public function subscribe($subscriber) : void
    {
        if (is_object($subscriber)) {
            $subscriber->subscribe($this);
            return;
        }
        $this->subscribe(new $subscriber);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $event
     * @param  array  $payload
     * @return array|null
     */
    public function until($event, $payload = []) : ?array
    {
        return $this->dispatch($event, $payload, true);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $event
     * @return void
     */
    public function flush($event) : void
    {
        $this->dispatch($event.'_pushed');
    }

    /**
     * {@inheritdoc}
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false) : ?array
    {
        $results = [];
        $payload = (array)$payload;
        if (is_object($event)) {
            array_unshift($payload, $event);
        }
        foreach ($this->getOnListeners($this->confirmEvent($event)) as $listener) {
            $result = call_user_func($listener, ...(array)$payload);
            if ($halt) {
                return $result;
            }
            $results[] = $result;
        }
        return $results ?: null;
    }


    /**
     * {@inheritdoc}
     *
     * @param  string  $event
     * @return void
     */
    public function forget($event) : void
    {
        throw new ImplementationException('Method forget() is not supported by this dispatcher');
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function forgetPushed() : void
    {
        throw new ImplementationException('Method forgetPushed() is not supported by this dispatcher');
    }
}