<?php
/**
 *  * Created by mtils on 05.09.2022 at 19:40.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\ChattyTrait;
use Koansu\Core\Contracts\Chatty;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\TestCase;

class ChattyTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(Chatty::class, $this->make());
    }

    /**
     * @test
     */
    public function it_emits_messages()
    {
        $chatty = $this->make();

        $logger1 = new LoggingCallable();
        $logger2 = new LoggingCallable();

        $chatty->onMessage($logger1);
        $chatty->onMessage($logger2);

        $chatty->bubble('Hello');

        $this->assertEquals('Hello', $logger1->arg(0));
        $this->assertEquals('Hello', $logger2->arg(0));
    }

    /**
     * @test
     */
    public function it_emits_level_and_args()
    {
        $chatty = $this->make();

        $logger1 = new LoggingCallable();
        $logger2 = new LoggingCallable();

        $chatty->onMessage($logger1);
        $chatty->onMessage($logger2);

        $chatty->bubble('Hello', Chatty::WARNING, ['foo']);

        $this->assertEquals('Hello', $logger1->arg(0));
        $this->assertEquals(Chatty::WARNING, $logger1->arg(1));
        $this->assertEquals(['foo'], $logger1->arg(2));

        $this->assertEquals('Hello', $logger2->arg(0));
        $this->assertEquals(Chatty::WARNING, $logger2->arg(1));
        $this->assertEquals(['foo'], $logger2->arg(2));
    }

    protected function make()
    {
        return new class implements Chatty
        {
            use ChattyTrait;

            function bubble(string $message, string $level=Chatty::INFO, array $extra=[])
            {
                $this->say($message, $level, $extra);
            }
        };
    }
}
