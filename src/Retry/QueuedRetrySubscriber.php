<?php

namespace GuzzleHttp\Retry;


use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\EventTriggerInterface;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Queue\Queue\ClientQueueInterface;
use GuzzleHttp\Queue\ResponsePromise;

class QueuedRetrySubscriber implements SubscriberInterface, EventTriggerInterface
{

    /** @var  ClientQueueInterface */
    protected $queue;

    /** @var  Callable */
    protected $filterFn;

    /** @var array Array of custom events that are triggered by this class */
    protected $customEventNames = ['requeue'];

    function __construct(ClientQueueInterface $queue, array $config = [])
    {
        $this->queue = $queue;
        $config += [
            'filterFn' => null,
            'max'      => 5,
        ];

        $this->filterFn   = $config['filterFn'];
        $this->maxRetries = $config['max'];
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
            'complete' => ['completeListener', 'first'],
            'error'    => ['completeListener', 'first'],
        ];
    }

    public function completeListener(AbstractTransferEvent $event)
    {
        $filterFn = $this->filterFn;
        $retries = $event->getRequest()->getConfig()->get('retries');
        if(!isset($retries)){
            $event->getRequest()->getConfig()->set('retries', 0);
            $retries = 0;
        }

        if ($retries < $this->maxRetries && $filterFn($retries, $event)) {
            $event->getRequest()->getConfig()->set('retries', $event->getRequest()->getConfig()->get('retries') + 1);
            $this->queue->enqueue($event->getRequest());
        }
    }


    /**
     * Returns an array of events that can be triggered by this class
     *
     * @return array
     */
    public function getTriggeredEvents()
    {
        return $this->customEventNames;
    }
}