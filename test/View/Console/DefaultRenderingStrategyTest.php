<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Mvc\View\Console;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Console\Adapter\AbstractAdapter;
use Zend\EventManager\EventManager;
use Zend\Mvc\ApplicationInterface;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\View\Console\DefaultRenderingStrategy;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Response;
use Zend\View\Model;
use ZendTest\Mvc\EventManagerIntrospectionTrait;

class DefaultRenderingStrategyTest extends TestCase
{
    use EventManagerIntrospectionTrait;

    /* @var DefaultRenderingStrategy */
    protected $strategy;

    public function setUp()
    {
        $this->strategy = new DefaultRenderingStrategy();
    }

    public function testAttachesRendererAtExpectedPriority()
    {
        $events = new EventManager();
        $this->strategy->attach($events);
        $listeners = $this->getListenersForEvent(MvcEvent::EVENT_RENDER, $events, true);

        $expectedListener = [$this->strategy, 'render'];
        $expectedPriority = -10000;
        $found            = false;

        /* @var \Zend\Stdlib\CallbackHandler $listener */
        foreach ($listeners as $priority => $listener) {
            if ($listener === $expectedListener
                && $priority === $expectedPriority
            ) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Renderer not found');
    }

    public function testCanDetachListenersFromEventManager()
    {
        $events = new EventManager();
        $this->strategy->attach($events);

        $listeners = iterator_to_array($this->getListenersForEvent(MvcEvent::EVENT_RENDER, $events));
        $this->assertCount(1, $listeners);

        $this->strategy->detach($events);
        $listeners = iterator_to_array($this->getListenersForEvent(MvcEvent::EVENT_RENDER, $events));
        $this->assertCount(0, $listeners);
    }

    public function testIgnoresNonConsoleModelNotContainingResultKeyWhenObtainingResult()
    {
        $console = $this->getMock(AbstractAdapter::class);
        $console
            ->expects($this->any())
            ->method('encodeText')
            ->willReturnArgument(0);

        //Register console service
        $sm = new ServiceManager();
        $sm->setService('console', $console);

        /* @var \PHPUnit_Framework_MockObject_MockObject|ApplicationInterface $mockApplication */
        $mockApplication = $this->getMock(ApplicationInterface::class);
        $mockApplication
            ->expects($this->any())
            ->method('getServiceManager')
            ->willReturn($sm);

        $event    = new MvcEvent();
        $event->setApplication($mockApplication);

        $model    = new Model\ViewModel(['content' => 'Page not found']);
        $response = new Response();
        $event->setResult($model);
        $event->setResponse($response);
        $this->strategy->render($event);
        $content = $response->getContent();
        $this->assertNotContains('Page not found', $content);
    }

    public function testIgnoresNonModel()
    {
        $console = $this->getMock(AbstractAdapter::class);
        $console
            ->expects($this->any())
            ->method('encodeText')
            ->willReturnArgument(0);

        //Register console service
        $sm = new ServiceManager();
        $sm->setService('console', $console);

        /* @var \PHPUnit_Framework_MockObject_MockObject|ApplicationInterface $mockApplication */
        $mockApplication = $this->getMock(ApplicationInterface::class);
        $mockApplication
            ->expects($this->any())
            ->method('getServiceManager')
            ->willReturn($sm);

        $event    = new MvcEvent();
        $event->setApplication($mockApplication);

        $model    = true;
        $response = new Response();
        $event->setResult($model);
        $event->setResponse($response);
        $this->assertSame($response, $this->strategy->render($event));
    }
}
