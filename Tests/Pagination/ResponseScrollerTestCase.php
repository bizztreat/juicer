<?php

namespace Bizztreat\Juicer\Tests\Pagination;

use Bizztreat\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;

class ResponseScrollerTestCase extends TestCase
{
    protected function getConfig()
    {
        return new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
    }
}
