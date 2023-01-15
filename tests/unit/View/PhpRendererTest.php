<?php
/**
 *  * Created by mtils on 18.12.2022 at 13:37.
 **/

namespace Koansu\Tests\View;

use Koansu\Core\RenderData;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;

use Koansu\View\PhpRenderer;

use Koansu\View\TemplateFinder;

use function json_encode;

class PhpRendererTest extends TestCase
{
    use TestData;

    /**
     * @test
     */
    public function it_implements_interface()
    {
        $this->assertTrue(is_callable($this->make()));
    }

    /**
     * @test
     */
    public function it_renders_view()
    {
        $renderer = $this->make();
        $template = new RenderData('users.index');
        $users = [
            'tils@ipo.de',
            'michael@tils.de',
            'foo@bar.de'
        ];
        $template->assign(['users' => $users]);

        $this->assertEquals(json_encode($users), $renderer($template));
    }

    /**
     * @param TemplateFinder|null $finder
     * @return PhpRenderer
     */
    protected function make(TemplateFinder $finder=null) : PhpRenderer
    {
        return new PhpRenderer($finder ?: $this->finder());
    }

    protected function finder() : TemplateFinder
    {
        return (new TemplateFinder())->addPath($this->dirOfTests('templates'));
    }
}