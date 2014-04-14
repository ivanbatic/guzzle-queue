<?php
/**
 * Created by PhpStorm.
 * User: ivan
 * Date: 4/4/14
 * Time: 3:19 PM
 */

namespace GuzzleHttp\Queue\Queue;


use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\AbstractRequestEvent;
use GuzzleHttp\Message\RequestInterface;

class HostnameQueue implements ClientQueueInterface
{

    /** @var array */
    protected $queue = [];

    /** @var array */
    protected $running = [];

    /** @var int Limits the number of simultaneous requests to a single hostname */
    protected $batchLimit = 10;

    /** @var  Client */
    protected $client;

    /**
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function enqueue(RequestInterface $request)
    {
        $this->listenToRequestEnd($request);
        $host = $request->getHost();
        if (!isset($this->queue[$host])) {
            $this->queue[$host] = [];
        }
        if (in_array($request, $this->queue[$host], true)) {
            return false;
        }
        $this->queue[$host][] = $request;

        $this->advanceLane($request->getHost());
    }

    private function advanceLane($host = null)
    {
        $existingHostnames = array_keys($this->queue);
        if (!$host) {
            $hostnames = $existingHostnames;
        } else if (is_string($host)) {
            $hostnames = array_intersect($existingHostnames, [$host]);
        } else if (is_array($host)) {
            $hostnames = array_interset($existingHostnames, $host);
        }

        foreach ($hostnames as $hostname) {
            // Break if there is nothing in the queue lane
            if (!isset($this->queue[$hostname]) || empty($this->queue[$hostname])) {
                continue;
            }

            // See how much space there is in the running lane
            $slots                    = $this->batchLimit;
            $this->running[$hostname] = isset($this->running[$hostname]) ? $this->running[$hostname] : [];

            if (!empty($this->running[$hostname])) {
                $slots -= count($this->running[$hostname]);
            }

            $queueSlice = array_slice($this->queue[$hostname], 0, $slots);

            $this->queue[$hostname]   = array_diff($this->queue[$hostname], $queueSlice);
            $this->running[$hostname] = array_merge($this->running[$hostname], $queueSlice);

            $this->client->appendRequests($queueSlice);
        }
    }

    /**
     * Advance the queue
     *
     * @param null $hostname
     */
    public function requestEndCallback(AbstractRequestEvent $event = null)
    {
        $hostname = $event->getRequest()->getHost();
        if(isset($this->running[$hostname])){
            $runnerKey = array_search($event->getRequest(), $this->running[$hostname]);
            if ($runnerKey !== false) {
                unset($this->running[$hostname]);
            }
        }

        $this->advanceLane($hostname);

    }

    public function listenToRequestEnd(RequestInterface &$request)
    {
        $request->getEmitter()->on('complete', [$this, 'requestEndCallback']);
        $request->getEmitter()->on('error', [$this, 'requestEndCallback']);
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function dequeue(RequestInterface $request)
    {
        // Take the hostname so we can categorize the request
        $host = $request->getHost();

        // Check if request is in queue. If so, remove it.
        if (isset($this->queue[$host])
            && false !== $key = array_search($request, $this->queue[$host], true)
        ) {
            unset($this->queue[$host][$key]);
        }

    }

    /**
     * @param int $batchLimit
     */
    public function setBatchLimit($batchLimit)
    {
        $this->batchLimit = (int)$batchLimit;
    }

    /**
     * @return int
     */
    public function getBatchLimit()
    {
        return $this->batchLimit;
    }

    /**
     * Counts queued requests
     *
     * @return int
     */
    public function count()
    {
        return array_sum(array_map('count', $this->queue));
    }

    /**
     * @return ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
    }

    protected function insertIntoProcess()
    {
    }
}