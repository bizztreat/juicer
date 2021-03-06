<?php

namespace Bizztreat\Juicer\Pagination\Decorator;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Pagination\ScrollerInterface;
use Bizztreat\Juicer\Config\JobConfig;
use Bizztreat\Juicer\Exception\UserException;

/**
 * Class HasMoreScrollerDecorator
 * Adds 'nextPageFlag' option to look at a boolean field in response to continue/stop scrolling
 */
class HasMoreScrollerDecorator extends AbstractScrollerDecorator
{
    /**
     * @var string
     */
    protected $field = null;

    /**
     * @var bool
     */
    protected $stopOn = false;

    /**
     * @var bool
     */
    protected $ifNotSet = false;

    /**
     * HasMoreScrollerDecorator constructor.
     * @param ScrollerInterface $scroller
     * @param array $config array with `nextPageFlag` item which is:
     *      [
     *          'field' => string // name of the boolean field
     *          'stopOn' => bool // whether to stop if the field value is true or false
     *          'ifNotSet' => bool // what value to assume if the field is not present
     *      ]
     * @throws UserException
     */
    public function __construct(ScrollerInterface $scroller, array $config)
    {
        if (!empty($config['nextPageFlag'])) {
            if (empty($config['nextPageFlag']['field'])) {
                throw new UserException("'field' has to be specified for 'nextPageFlag'");
            }

            if (!isset($config['nextPageFlag']['stopOn'])) {
                throw new UserException("'stopOn' value must be set to a boolean value for 'nextPageFlag'");
            }

            $this->field = $config['nextPageFlag']['field'];
            $this->stopOn = $config['nextPageFlag']['stopOn'];
            if (isset($config['nextPageFlag']['ifNotSet'])) {
                $this->ifNotSet = $config['nextPageFlag']['ifNotSet'];
            } else {
                $this->ifNotSet = $this->stopOn;
            }
        }

        parent::__construct($scroller);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        if (false === $this->hasMore($response)) {
            return false;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @param mixed $response
     * @return bool|null Returns null if this option isn't used
     */
    protected function hasMore($response)
    {
        if (empty($this->field)) {
            return null;
        }

        if (!isset($response->{$this->field})) {
            $value = $this->ifNotSet;
        } else {
            $value = $response->{$this->field};
        }

        if ((bool)$value === $this->stopOn) {
            return false;
        } else {
            return true;
        }
    }
}
