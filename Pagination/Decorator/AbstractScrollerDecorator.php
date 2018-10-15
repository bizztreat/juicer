<?php

namespace Bizztreat\Juicer\Pagination\Decorator;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Pagination\ScrollerInterface;
use Bizztreat\Juicer\Config\JobConfig;

abstract class AbstractScrollerDecorator implements ScrollerInterface
{
    /**
     * @var ScrollerInterface
     */
    protected $scroller;

    /**
     * AbstractScrollerDecorator constructor.
     * @param ScrollerInterface $scroller
     */
    public function __construct(ScrollerInterface $scroller)
    {
        $this->scroller = $scroller;
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->scroller->reset();
    }

    /**
     * Get decorated scroller
     * @return ScrollerInterface
     */
    public function getScroller()
    {
        return $this->scroller;
    }

    /**
     * @inheritdoc
     */
    public function getState()
    {
        return [
            'decorator' => get_object_vars($this),
            'scroller' => get_object_vars($this->scroller)
        ];
    }

    /**
     * @inheritdoc
     */
    public function setState(array $state)
    {
        if (isset($state['scroller'])) {
            $this->scroller->setState($state['scroller']);
        }

        foreach (array_keys(get_object_vars($this)) as $key) {
            if (isset($state['decorator'][$key])) {
                $this->{$key} = $state['decorator'][$key];
            }
        }
    }
}
