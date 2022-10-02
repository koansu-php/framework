<?php
/**
 *  * Created by mtils on 02.10.2022 at 09:03.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\Contracts\PublishesProgress;
use Koansu\Core\Progress;
use Koansu\Core\PublishesProgressTrait;
use Koansu\Testing\LoggingCallable;
use Koansu\Tests\TestCase;

class PublishesProgressTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertInstanceOf(PublishesProgress::class, $this->command());
    }

    /**
     * @test
     */
    public function emitProgress_emits_progress_to_listeners()
    {
        $listener = new LoggingCallable();
        $listener2 = new LoggingCallable();

        $command = $this->command();

        $command->onProgressChanged($listener);
        $command->onProgressChanged($listener2);

        $command->emit(70, 2, 4, 'copy', 3600);

        $this->assertCount(1, $listener);
        $this->assertCount(1, $listener2);

        $progress = $listener->arg(0);
        $this->assertSame($progress, $listener2->arg(0));

        $this->assertInstanceOf(Progress::class, $progress);
        $this->assertEquals(70, $progress->percent);
        $this->assertEquals(2, $progress->step);
        $this->assertEquals(4, $progress->totalSteps);
        $this->assertEquals('copy', $progress->stepName);
        $this->assertEquals(3600, $progress->leftOverSeconds);

    }

    /**
     * @test
     */
    public function emitProgress_emits_progress_object_to_listeners()
    {
        $listener = new LoggingCallable();
        $listener2 = new LoggingCallable();

        $command = $this->command();

        $command->onProgressChanged($listener);
        $command->onProgressChanged($listener2);

        $progress = new Progress(70, 2, 4, 'copy', 3600);

        $command->emit($progress);

        $this->assertCount(1, $listener);
        $this->assertCount(1, $listener2);

        $this->assertSame($progress, $listener->arg(0));
        $this->assertSame($progress, $listener2->arg(0));
    }

    protected function command() : ProgressCommand
    {
        return new ProgressCommand();
    }
}

class ProgressCommand implements PublishesProgress
{
    use PublishesProgressTrait;

    public function emit($progress, int $step=0, int $totalSteps=1, string $stepName='', int $leftOverSeconds=0)
    {
        $this->emitProgress($progress, $step, $totalSteps, $stepName, $leftOverSeconds);
    }
}
