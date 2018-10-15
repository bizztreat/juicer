<?php

namespace Bizztreat\Juicer\Tests\Pagination\Decorator;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Config\JobConfig;
use Bizztreat\Juicer\Pagination\PageScroller;
use Bizztreat\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ForceStopScrollerDecoratorTest extends TestCase
{
    /**
     * @dataProvider limitProvider
     * @param array $config
     * @param array|object $response
     */
    public function testCheckLimits(array $config, $response)
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig([
            'endpoint' => 'test'
        ]);

        $scroller = new PageScroller([]);

        $decorator = new ForceStopScrollerDecorator($scroller, [
            'forceStop' => $config
        ]);

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, $response, $response)) {
            self::assertInstanceOf('Bizztreat\Juicer\Client\RestRequest', $request);
            $i++;
        }
        self::assertFalse($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);
    }

    public function limitProvider()
    {
        $response = [
            (object)[
                'asdf' => 1234
            ]
        ];

        return [
            'pages' => [
                ['pages' => 3],
                $response
            ],
            'volume' => [
                ['volume' => strlen(json_encode($response)) * 3],
                $response
            ]
        ];
    }

    public function testTimeLimit()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig([
            'endpoint' => 'test'
        ]);

        $scroller = new PageScroller([]);

        $decorator = new ForceStopScrollerDecorator($scroller, [
            'forceStop' => [
                'time' => 3
            ]
        ]);

        $response = ['a'];

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, [$response], $response)) {
            self::assertInstanceOf('Bizztreat\Juicer\Client\RestRequest', $request);
            $i++;
            sleep(1);
        }
        self::assertFalse($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);
    }
}
