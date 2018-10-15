<?php

namespace Bizztreat\Juicer\Pagination\Decorator;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Client\RestRequest;
use Bizztreat\Juicer\Exception\UserException;
use Bizztreat\Juicer\Pagination\ScrollerInterface;
use Bizztreat\Juicer\Config\JobConfig;

/**
 * Class LimitStopScrollerDecorator
 * Adds 'limit' option
 */
class LimitStopScrollerDecorator extends AbstractScrollerDecorator
{
    /**
     * @var int
     */
    private $countLimit;

    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var int
     */
    private $currentCount;

    /**
     * Constructor.
     * @param ScrollerInterface $scroller
     * @param array $config
     * @throws UserException
     */
    public function __construct(ScrollerInterface $scroller, array $config)
    {
        parent::__construct($scroller);
        if (!empty($config['limitStop'])) {
            if (empty($config['limitStop']['field']) && empty($config['limitStop']['count'])) {
                throw new UserException("One of 'limitStop.field' or 'limitStop.count' attributes is required.");
            }
            if (!empty($config['limitStop']['field']) && !empty($config['limitStop']['count'])) {
                throw new UserException("Specify only one of 'limitStop.field' or 'limitStop.count'.");
            }
            if (!empty($config['limitStop']['field'])) {
                $this->fieldName = $config['limitStop']['field'];
            }
            if (!empty($config['limitStop']['count'])) {
                $this->countLimit = intval($config['limitStop']['count']);
            }
        }
        $this->reset();
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        $this->currentCount = 0;
        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        $this->currentCount += count($data);
        if ($this->fieldName) {
            $limit = \Keboola\Utils\getDataFromPath($this->fieldName, $response, '.');
        } else {
            $limit = $this->countLimit;
        }
        if ($this->currentCount >= $limit) {
            return false;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->currentCount = 0;
        parent::reset();
    }
}
