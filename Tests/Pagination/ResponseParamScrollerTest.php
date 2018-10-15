<?php

namespace Bizztreat\Juicer\Tests\Pagination;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Exception\UserException;
use Bizztreat\Juicer\Pagination\ResponseParamScroller;
use Psr\Log\NullLogger;

class ResponseParamScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest()
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id'
        ]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'scroll_id' => 'asdf'
            ]
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new \stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
    }

    public function testGetNextRequestNested()
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => 'scroll.id',
            'queryParam' => 'scroll_id'
        ]);

        $response = (object) [
            'scroll' => (object) [
                'id' => 'asdf'
            ]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, []);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'scroll_id' => 'asdf'
            ]
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestOverride()
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => false,
            'scrollRequest' => [
                'endpoint' => '_search/scroll',
                'method' => 'POST',
                'params' => [
                    'scroll' => '1m'
                ]
            ]
        ]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => '_search/scroll',
            'params' => [
                'scroll' => '1m',
                'scroll_id' => 'asdf'
            ],
            'method' => 'POST'
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestParams()
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $scrollerParams = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => true,
            'scrollRequest' => [
                'params' => [
                    'scroll' => '1m'
                ]
            ]
        ]);

        $nextParams = $scrollerParams->getNextRequest($client, $config, $response, $response->data);
        $expectedParams = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'scroll' => '1m',
                'scroll_id' => 'asdf'
            ]
        ]);
        self::assertEquals($expectedParams, $nextParams);
    }

    public function testGetFirstRequest()
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller(['responseParam' => '_scroll_id', 'queryParam' => 'scroll_id', 'includeParams' => false, 'scrollRequest' => [
            'endpoint' => '_search/scroll',
            'method' => 'POST',
            'params' => [
                'scroll' => '1m'
            ]
        ]]);

        $expected = $client->createRequest($config->getConfig());
        self::assertEquals($expected, $scroller->getFirstRequest($client, $config));
    }

    public function testInvalid()
    {
        try {
            new ResponseParamScroller([]);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Missing required \'pagination.responseParam\' parameter', $e->getMessage());
        }
        try {
            new ResponseParamScroller(['responseParam' => 'foo']);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Missing required \'pagination.queryParam\' parameter', $e->getMessage());
        }
        new ResponseParamScroller(['responseParam' => 'foo', 'queryParam' => 'bar']);
    }
}
