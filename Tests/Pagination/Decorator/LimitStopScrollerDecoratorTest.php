<?php

namespace Bizztreat\Juicer\Tests\Pagination\Decorator;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Client\RestRequest;
use Bizztreat\Juicer\Config\JobConfig;
use Bizztreat\Juicer\Pagination\Decorator\LimitStopScrollerDecorator;
use Bizztreat\Juicer\Pagination\NoScroller;
use Bizztreat\Juicer\Pagination\PageScroller;
use Bizztreat\Juicer\Tests\ExtractorTestCase;
use Psr\Log\NullLogger;

class LimitStopScrollerDecoratorTest extends ExtractorTestCase
{
    public function testField()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['field' => 'results.totalNumber']];

        $scroller = new PageScroller(['pageParam' => 'pageNo']);
        $decorated = new LimitStopScrollerDecorator($scroller, $config);
        $response = new \stdClass();
        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object)['key' => 'value']);

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertInstanceOf(RestRequest::class, $next);
        self::assertInstanceOf(PageScroller::class, $decorated->getScroller());

        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 2];
        $response->results->data = array_fill(0, 5, (object)['key' => 'value2']);
        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertFalse($noNext);
    }

    public function testLimit()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['count' => 12]];

        $scroller = new PageScroller(['pageParam' => 'pageNo']);
        $decorated = new LimitStopScrollerDecorator($scroller, $config);
        $response = new \stdClass();
        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object)['key' => 'value']);

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertInstanceOf(RestRequest::class, $next);
        self::assertInstanceOf(PageScroller::class, $decorated->getScroller());

        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 2];
        $response->results->data = array_fill(0, 5, (object)['key' => 'value2']);
        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertFalse($noNext);
    }

    /**
     * @expectedException \Bizztreat\Juicer\Exception\UserException
     * @expectedExceptionMessage One of 'limitStop.field' or 'limitStop.count' attributes is required.
     */
    public function testInvalid1()
    {
        new LimitStopScrollerDecorator(new NoScroller(), ['limitStop' => ['count' => 0]]);
    }

    /**
     * @expectedException \Bizztreat\Juicer\Exception\UserException
     * @expectedExceptionMessage Specify only one of 'limitStop.field' or 'limitStop.count'
     */
    public function testInvalid2()
    {
        new LimitStopScrollerDecorator(new NoScroller(), ['limitStop' => ['count' => 12, 'field' => 'whatever']]);
    }
}
