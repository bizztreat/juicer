<?php

namespace Bizztreat\Juicer\Tests\Pagination;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Config\JobConfig;
use Bizztreat\Juicer\Exception\UserException;
use Bizztreat\Juicer\Pagination\OffsetScroller;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class OffsetScrollerTest extends TestCase
{
    public function testGetNextRequest()
    {
        $client = new RestClient(new NullLogger());
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = new OffsetScroller(['limit' => 10, 'limitParam' => 'max', 'offsetParam' => 'startAt']);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'max' => 10,
                'startAt' => 10
            ]
        ]);
        self::assertEquals($expected, $next);

        $next2 = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected2 = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'max' => 10,
                'startAt' => 20
            ]
        ]);
        self::assertEquals($expected2, $next2);

        $responseUnderLimit = new \stdClass();
        $responseUnderLimit->data = array_fill(0, 5, (object) ['key' => 'value']);
        $next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
        self::assertEquals(false, $next3);

        // this should be in a separate testReset()
        // must match the first one, because #3 should reset the scroller
        $next4 = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($expected, $next4);
    }

    public function testGetFirstRequest()
    {
        $client = new RestClient(new NullLogger());
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
        $limit = 10;

        $scroller = new OffsetScroller(['limit' => $limit]);
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => array_merge(
                $config->getParams(),
                [
                    'limit' => $limit,
                    'offset' => 0
                ]
            )
        ]);
        self::assertEquals($expected, $req);

        $noParamsScroller = new OffsetScroller([
            'limit' => $limit,
            'limitParam' => 'count',
            'offsetParam' => 'first',
            'firstPageParams' => false
        ]);
        $noParamsRequest = $noParamsScroller->getFirstRequest($client, $config);
        $noParamsExpected = $client->createRequest($config->getConfig());
        self::assertEquals($noParamsExpected, $noParamsRequest);
    }

    public function testOffsetFromJob()
    {
        $client = new RestClient(new NullLogger());
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'startAt' => 3
            ]
        ]);
        $limit = 10;

        $scroller = new OffsetScroller([
            'limit' => $limit,
            'offsetFromJob' => true,
            'offsetParam' => 'startAt'
        ]);

        $first = $scroller->getFirstRequest($client, $config);

        self::assertEquals($config->getParams()['startAt'], $first->getParams()['startAt']);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $second = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($config->getParams()['startAt'] + $limit, $second->getParams()['startAt']);
    }

    public function testInvalid()
    {
        try {
            new OffsetScroller([]);
            self::fail("Must cause exception");
        } catch (UserException $e) {
            self::assertContains('Missing \'pagination.limit\' attribute required for offset pagination', $e->getMessage());
        }
        new OffsetScroller(['limit' => 'foo']);
    }
}
