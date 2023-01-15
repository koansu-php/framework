<?php
/**
 *  * Created by mtils on 03.12.2022 at 13:01.
 **/

namespace Koansu\Tests\Skeleton;

use Koansu\Core\ChattyTrait;
use Koansu\Core\Contracts\Chatty;
use Koansu\Core\Str;
use Koansu\Core\Stream;
use Koansu\Skeleton\StreamLogger;
use Koansu\Tests\TestCase;
use Psr\Log\LoggerInterface;

use function strtolower;

class StreamLoggerTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->make());
    }

    /**
     * @test
     */
    public function get_and_set_target()
    {
        $logger = $this->make();
        $string = '';
        $stream = new Stream($string);
        $this->assertSame($logger, $logger->setTarget($stream));
        $this->assertSame($stream, $logger->getTarget());
    }

    /**
     * @test
     */
    public function emergency_does_log()
    {
        $stream = new Stream(new Str(''), 'w+');
        $logger = $this->make($stream);
        $logger->emergency('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function alert_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->alert('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function critical_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->critical('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function error_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->error('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function warning_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->warning('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function notice_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->notice('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function info_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->info('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function debug_does_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->debug('Boom');
        $this->assertStringContainsString('Boom', "$stream");
    }

    /**
     * @test
     */
    public function it_does_log_context()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);
        $logger->debug('Boom', ['foo' => 'bar']);
        $this->assertStringContainsString('Boom', "$stream");
        $this->assertStringContainsString('bar', "$stream");

    }

    /**
     * @test
     */
    public function it_forwards_chatty_to_log()
    {
        $stream = new Stream(new Str(''));
        $logger = $this->make($stream);

        $chatty = new StreamLoggerTest_Chatty();
        $logger->forward($chatty);

        $chatty->trigger('Hello', Chatty::INFO);

        $this->assertStringContainsString('hello', strtolower($stream));

    }

    protected function make($target='php://temp') : StreamLogger
    {
        return new StreamLogger($target);
    }
}

class StreamLoggerTest_Chatty implements Chatty
{
    use ChattyTrait;

    public function trigger($message, $level=Chatty::INFO)
    {
        $this->say($message, $level);
    }
}