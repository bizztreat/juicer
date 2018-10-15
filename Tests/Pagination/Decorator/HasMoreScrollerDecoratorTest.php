<?php

namespace Bizztreat\Juicer\Tests\Pagination\Decorator;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Config\JobConfig;
use Bizztreat\Juicer\Pagination\OffsetScroller;
use Bizztreat\Juicer\Pagination\NoScroller;
use Bizztreat\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Bizztreat\Juicer\Tests\ExtractorTestCase;
use Psr\Log\NullLogger;

class HasMoreScrollerDecoratorTest extends ExtractorTestCase
{
    public function testGetNextRequestHasMore()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = [
            'nextPageFlag' => [
                'field' => 'hasMore',
                'stopOn' => false
            ]
        ];

        $scroller = new OffsetScroller(['limit' => 10]);

        $decorated = new HasMoreScrollerDecorator($scroller, $config);
        self::assertInstanceOf('Bizztreat\Juicer\Pagination\OffsetScroller', $decorated->getScroller());

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => true],
            array_fill(0, 10, ['k' => 'v'])
        );
        self::assertInstanceOf('Bizztreat\Juicer\Client\RestRequest', $next);

        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => false],
            array_fill(0, 10, ['k' => 'v'])
        );
        self::assertFalse($noNext);
    }

    public function testHasMore()
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, [
            'nextPageFlag' => [
                'field' => 'finished',
                'stopOn' => true
            ]
        ]);

        $yes = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertTrue($yes);
        $no = self::callMethod($scroller, 'hasMore', [(object) ['finished' => true]]);
        self::assertFalse($no);
    }

    public function testHasMoreNotSet()
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, []);

        $null = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertNull($null);
    }
}
