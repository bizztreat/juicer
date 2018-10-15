<?php

namespace Bizztreat\Juicer\Pagination;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Config\JobConfig;

/**
 * Scrolls using URL or Endpoint within page's response.
 */
abstract class AbstractResponseScroller extends AbstractScroller
{
    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
    }
}
