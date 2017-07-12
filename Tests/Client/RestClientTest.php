<?php

namespace Keboola\Juicer\Tests\Client;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

class RestClientTest extends ExtractorTestCase
{
    public function testCreateRequest()
    {
        $arr = [
            'first' => 1,
            'second' => 'two'
        ];
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'params' => $arr
        ]);

        $client = new RestClient(new Client, new NullLogger());
        $request = $client->createRequest($jobConfig->getConfig());

        $expected = new RestRequest('ep', $arr);

        self::assertEquals($expected, $request);
    }

    public function testGetGuzzleRequest()
    {
        $client = new RestClient(new Client, new NullLogger());
        $requestGet = new RestRequest('ep', ['a' => 1]);
        $requestPost = new RestRequest('ep', ['a' => 1], 'POST');
        $requestForm = new RestRequest('ep', ['a' => 1], 'FORM');

        $get = self::callMethod($client, 'getGuzzleRequest', [$requestGet]);
        $post = self::callMethod($client, 'getGuzzleRequest', [$requestPost]);
        $form = self::callMethod($client, 'getGuzzleRequest', [$requestForm]);

        self::assertEquals('ep?a=1', $get->getUrl());

        self::assertEquals('ep', $post->getUrl());
        self::assertEquals('{"a":1}', $post->getBody());

        self::assertEquals('ep', $form->getUrl());
        self::assertEquals(['a' => 1], $form->getBody()->getFields());
    }

    public function testDownload()
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $guzzle = new Client();
        $guzzle->setDefaultOption('headers', ['X-Test' => '1234']);

        $mock = new Mock([
            new Response(200, [], Stream::factory($body))
        ]);
        $guzzle->getEmitter()->attach($mock);

        $history = new History();
        $guzzle->getEmitter()->attach($history);

        $restClient = new RestClient($guzzle, new NullLogger());

        $request = new RestRequest('ep', ['a' => 1]);

        self::assertEquals(json_decode($body), $restClient->download($request));
        self::assertEquals('ep?a=1', $history->getLastRequest()->getUrl());
        self::assertEquals('GET', $history->getLastRequest()->getMethod());
        self::assertEquals(
            [1234],
            $history->getLastRequest()->getHeaders()['X-Test']
        );
    }

    public function testRequestHeaders()
    {
        $guzzle = new Client();
        $guzzle->setDefaultOption('headers', ['X-Test' => '1234']);

        $mock = new Mock([
            new Response(200, [], Stream::factory('{}'))
        ]);
        $guzzle->getEmitter()->attach($mock);

        $history = new History();
        $guzzle->getEmitter()->attach($history);

        $restClient = new RestClient($guzzle, new NullLogger());

        $request = new RestRequest('ep', [], 'GET', ['X-RTest' => 'requestHeader']);
        $restClient->download($request);

        self::assertEquals(
            [
                'X-RTest' => ['requestHeader'],
                'X-Test' => ['1234']
            ],
            $history->getLastRequest()->getHeaders()
        );
    }

    protected function runBackoff(RestClient $restClient, Response $errResponse)
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $mock = new Mock([
            $errResponse,
            new Response(200, [], Stream::factory($body))
        ]);
        $restClient->getClient()->getEmitter()->attach($mock);

        $history = new History();
        $restClient->getClient()->getEmitter()->attach($history);

        $request = new RestRequest('ep', ['a' => 1]);
        self::assertEquals(json_decode($body), $restClient->download($request));
        self::assertEquals(5000, $history->getLastRequest()->getConfig()['delay'], '', 1000);
    }

    /**
     * Cannot use dataProvider because that gets set up before all tests
     * and the delay causes issues
     */
    public function testStatusBackoff()
    {
        $sets = [
            'default' => [
                RestClient::create(new NullLogger()),
                new Response(429, ['Retry-After' => 5])
            ],
            'custom' => [
                RestClient::create(
                    new NullLogger(),
                    [],
                    [
                        'http' => [
                            'retryHeader' => 'X-Rate-Limit-Reset',
                            'codes' => [403, 429],
                        ],
                        'maxRetries' => 8
                    ]
                ),
                new Response(403, ['X-Rate-Limit-Reset' => 5])
            ],
            'absolute' => [
                RestClient::create(new NullLogger()),
                new Response(429, ['Retry-After' => time() + 5])
            ]
        ];

        foreach ($sets as $set) {
            $this->runBackoff($set[0], $set[1]);
        }
    }

    /**
     * Cannot use dataProvider because that gets set up before all tests
     * and the delay causes issues
     */
    public function testCurlBackoff()
    {
        // mapped curl error
        $retries = 3;
        $handler = new TestHandler();
        $logger = new Logger("test", [
            $handler
        ]);

        $client = RestClient::create(
            $logger,
            [],
            [
                'maxRetries' => $retries,
                'curl' => [
                    'codes' => [6],
                ]
            ]
        );

        try {
            $client->download(new RestRequest('http://keboolakeboolakeboola.com'));
            self::fail("Request should fail");
        } catch (\Exception $e) {
            self::assertCount($retries, $handler->getRecords());

            foreach ($handler->getRecords() as $record) {
                self::assertEquals(100, $record['level']);
                self::assertRegExp('/retrying/ui', $record['message']);
                self::assertRegExp('/curl error 6\:/ui', $record['context']['message']);
            }

            self::assertRegExp('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }

        // non-mapped curl error
        $retries = 3;
        $handler = new TestHandler();
        $logger = new Logger("test", [
            $handler
        ]);

        $client = RestClient::create(
            $logger,
            [],
            [
                'maxRetries' => $retries,
                'curl' => [
                    'codes' => [77],
                ]
            ]
        );

        try {
            $client->download(new RestRequest('http://keboolakeboolakeboola.com'));
            self::fail("Request should fail");
        } catch (\Exception $e) {
            self::assertCount(0, $handler->getRecords());
            self::assertRegExp('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Invalid JSON response from API: JSON decode error:
     */
    public function testMalformedJson()
    {
        $body = '[
                {"field": "d
        ]';

        $restClient = RestClient::create(new NullLogger());

        $mock = new Mock([
            new Response(200, [], Stream::factory($body))
        ]);
        $restClient->getClient()->getEmitter()->attach($mock);

        $request = new RestRequest('ep');

        try {
            $restClient->download($request);
        } catch (UserException $e) {
            self::assertArrayHasKey('errDetail', $e->getData());
            self::assertArrayHasKey('json', $e->getData());
            throw $e;
        }

        throw new \Exception;
    }

    public function testDefaultRequestOptions()
    {
        $defaultOptions = [
            'method' => 'POST',
            'params' => [
                'defA' => 'defValA',
                'defB' => 'defValB'
            ]
        ];

        $client = RestClient::create(new NullLogger());
        $client->setDefaultRequestOptions($defaultOptions);

        $requestOptions = [
            'endpoint' => 'ep',
            'params' => [
                'defB' => 'overrideB'
            ]
        ];
        $request = $client->createRequest($requestOptions);

        self::assertEquals($defaultOptions['method'], $request->getMethod());
        self::assertEquals($requestOptions['endpoint'], $request->getEndpoint());
        self::assertEquals(
            array_replace($defaultOptions['params'], $requestOptions['params']),
            $request->getParams()
        );
    }
}
