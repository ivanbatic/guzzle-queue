<?php

namespace GuzzleHttp\MultipointRequest;


use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Queue\Queue\ClientQueueInterface;

class MultipointRequestSubscriber implements SubscriberInterface
{

    /** @var  ClientQueueInterface */
    protected $queue;

    /** @var  array */
    protected $config = [
        'header'         => 'MWP-Multipoint-ID',
        'response_param' => 'multipoint_id'
    ];


    function __construct(ClientQueueInterface $queue, array $config = [])
    {
        $this->queue  = $queue;
        $this->config = array_merge($this->config, $config);
    }


    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The returned array keys MUST map to an event name. Each array value
     * MUST be an array in which the first element is the name of a function
     * on the EventSubscriber. The second element in the array is optional, and
     * if specified, designates the event priority.
     *
     * For example:
     *
     *  - ['eventName' => ['methodName']]
     *  - ['eventName' => ['methodName', $priority]]
     *
     * @return array
     */
    public function getEvents()
    {
        return [
            'complete' => ['completeListener', 'last'],
        ];
    }

    public function completeListener(AbstractTransferEvent $event)
    {
        $jsonResponse = $event->getResponse()->json();
        if (is_array($jsonResponse) && isset($jsonResponse[$this->config['response_param']])) {
            $nextRequest = clone $event->getRequest();
            $nextRequest->setHeader($this->config['header'], $jsonResponse[$this->config['response_param']]);
            $this->queue->enqueue($nextRequest);
        }
    }
}