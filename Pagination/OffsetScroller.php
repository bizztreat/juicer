<?php

namespace Bizztreat\Juicer\Pagination;

use Bizztreat\Juicer\Client\RestClient;
use Bizztreat\Juicer\Exception\UserException;
use Bizztreat\Juicer\Config\JobConfig;

/**
 * Scrolls using simple "limit" and "offset" query parameters.
 * Limit can be overridden in job's config's query parameters
 * and it will be used instead of extractor's default.
 * Offset can be overridden if 'offsetFromJob' is enabled
 */
class OffsetScroller extends AbstractScroller implements ScrollerInterface
{
    /**
     * @var int
     */
    protected $limit;

    /**
     * @var string
     */
    protected $limitParam = 'limit';

    /**
     * @var string
     */
    protected $offsetParam = 'offset';

    /**
     * @var bool
     */
    protected $firstPageParams = true;

    /**
     * @var int
     */
    protected $pointer = 0;

    /**
     * @var bool
     */
    protected $offsetFromJob = false;

    /**
     * OffsetScroller constructor.
     * @param array $config
     *      [
     *          'limit' => int // mandatory parameter; size of each page
     *          'limitParam' => string // the limit parameter (usually 'limit', 'count', ...)
     *          'offsetParam' => string // the offset parameter
     *          'firstPageParams' => bool // whether to include the limit and offset in the first request (default = true)
     *          'offsetFromJob' => bool // use offset parameter provided in the job parameters
     *      ]
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (empty($config['limit'])) {
            throw new UserException("Missing 'pagination.limit' attribute required for offset pagination");
        }
        $this->limit = $config['limit'];
        if (!empty($config['limitParam'])) {
            $this->limitParam = $config['limitParam'];
        }
        if (!empty($config['offsetParam'])) {
            $this->offsetParam = $config['offsetParam'];
        }
        if (isset($config['firstPageParams'])) {
            $this->firstPageParams = (bool)$config['firstPageParams'];
        }
        if (isset($config['offsetFromJob'])) {
            $this->offsetFromJob = (bool)$config['offsetFromJob'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        if ($this->offsetFromJob && !empty($jobConfig->getParams()[$this->offsetParam])) {
            $this->pointer = $jobConfig->getParams()[$this->offsetParam];
        }

        if ($this->firstPageParams) {
            $config = $this->getParams($jobConfig);
        } else {
            $config = $jobConfig->getConfig();
        }

        return $client->createRequest($config);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        if (count($data) < $this->getLimit($jobConfig)) {
            $this->reset();
            return false;
        } else {
            $this->pointer += $this->getLimit($jobConfig);

            return $client->createRequest($this->getParams($jobConfig));
        }
    }

    public function reset()
    {
        $this->pointer = 0;
    }

    /**
     * Returns a config with scroller params
     * @param JobConfig $jobConfig
     * @return array
     */
    private function getParams(JobConfig $jobConfig)
    {
        $config = $jobConfig->getConfig();
        $scrollParams = [
            $this->limitParam => $this->getLimit($jobConfig),
            $this->offsetParam => $this->pointer
        ];

        $config['params'] = array_replace($jobConfig->getParams(), $scrollParams);
        return $config;
    }

    /**
     * @param JobConfig $jobConfig
     * @return int
     */
    private function getLimit(JobConfig $jobConfig)
    {
        $params = $jobConfig->getParams();
        return empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];
    }
}
