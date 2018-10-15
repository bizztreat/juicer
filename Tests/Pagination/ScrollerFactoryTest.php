<?php

namespace Bizztreat\Juicer\Tests\Pagination;

use Bizztreat\Juicer\Exception\UserException;
use Bizztreat\Juicer\Pagination\CursorScroller;
use Bizztreat\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use Bizztreat\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Bizztreat\Juicer\Pagination\Decorator\LimitStopScrollerDecorator;
use Bizztreat\Juicer\Pagination\MultipleScroller;
use Bizztreat\Juicer\Pagination\NoScroller;
use Bizztreat\Juicer\Pagination\OffsetScroller;
use Bizztreat\Juicer\Pagination\PageScroller;
use Bizztreat\Juicer\Pagination\ResponseParamScroller;
use Bizztreat\Juicer\Pagination\ResponseUrlScroller;
use Bizztreat\Juicer\Pagination\ScrollerFactory;
use Bizztreat\Juicer\Pagination\ZendeskResponseUrlScroller;
use PHPUnit\Framework\TestCase;

class ScrollerFactoryTest extends TestCase
{
    public function testCreateScroller()
    {
        self::assertInstanceOf(NoScroller::class, ScrollerFactory::getScroller([]));
        self::assertInstanceOf(CursorScroller::class, ScrollerFactory::getScroller([
            'method' => 'cursor',
            'idKey' => 'id',
            'param' => 'from'
        ]));
        self::assertInstanceOf(OffsetScroller::class, ScrollerFactory::getScroller([
            'method' => 'offset',
            'limit' => 2
        ]));
        self::assertInstanceOf(PageScroller::class, ScrollerFactory::getScroller([
            'method' => 'pagenum'
        ]));
        self::assertInstanceOf(ResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'response.url'
        ]));
        self::assertInstanceOf(ResponseParamScroller::class, ScrollerFactory::getScroller([
            'method' => 'response.param',
            'responseParam' => 'scrollId',
            'queryParam' => 'scrollID'
        ]));
        self::assertInstanceOf(MultipleScroller::class, ScrollerFactory::getScroller([
            'method' => 'multiple',
            'scrollers' => ['none' => []]
        ]));
        self::assertInstanceOf(ZendeskResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'zendesk.response.url'
        ]));
    }

    public function testDecorateScroller()
    {
        self::assertInstanceOf(HasMoreScrollerDecorator::class, ScrollerFactory::getScroller([
            'nextPageFlag' => [
                'field' => 'continue',
                'stopOn' => 'false'
            ],
            'method' => 'pagenum'
        ]));
        self::assertInstanceOf(ForceStopScrollerDecorator::class, ScrollerFactory::getScroller([
            'forceStop' => [
                'pages' => 2
            ]
        ]));
        self::assertInstanceOf(LimitStopScrollerDecorator::class, ScrollerFactory::getScroller([
            'limitStop' => [
                'count' => 10
            ]
        ]));
    }

    public function testInvalid()
    {
        try {
            ScrollerFactory::getScroller(['method' => 'fooBar']);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Unknown pagination method \'fooBar\'', $e->getMessage());
        }
        try {
            ScrollerFactory::getScroller(['method' => ['foo' => 'bar']]);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Unknown pagination method \'{"foo":"bar"}\'', $e->getMessage());
        }
    }
}
