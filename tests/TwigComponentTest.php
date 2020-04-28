<?php

namespace tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Container;
use think\View;
use think\view\driver\Twig;
use Twig\TwigFilter;
use yunwuxin\mail\Markdown;
use yunwuxin\mail\twig\TokenParser\Component;

class TwigComponentTest extends TestCase
{
    /** @var App */
    protected $app;

    /** @var View */
    protected $view;

    /** @var Twig */
    protected $twig;

    protected function setUp()
    {
        $this->app = m::mock(App::class)->makePartial();

        Container::setInstance($this->app);
        $this->app->shouldReceive('make')->with(App::class)->andReturn($this->app);
        $this->app->shouldReceive('isDebug')->andReturnTrue();

        $this->view = new View($this->app);

        $this->twig    = $this->view->engine('twig');
        $parser        = new Markdown();
        $parser->html5 = true;
        $this->twig->getTwig()->addFilter(new TwigFilter('markdown', function ($content) use ($parser) {
            $content = preg_replace('/^[^\S\n]+/m', '', $content);
            return $parser->parse($content);
        }));

        $this->twig->getTwig()->addTokenParser(new Component());

        $this->twig->getLoader()->addPath(__DIR__ . '/../src/mail/resource/view', 'mail');

        $this->twig->getLoader()->addPath(__DIR__ . '/fixtures', 'fixtures');
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testNormalComponent()
    {
        echo $this->twig->getTwig()->render('@fixtures/normal.twig',
            ['level'      => 'aaa',
             'subject'    => 'bbbb',
             'greeting'   => 'ccc',
             'introLines' => ['aaa'],
             'outroLines' => ['aaa'],
             'actionText' => null,
             'actionUrl'  => null,
            ]);
    }
}
