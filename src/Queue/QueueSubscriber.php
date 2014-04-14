<?php

namespace GuzzleHttp\Queue;


use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EventTriggerInterface;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Queue\Event\EnqueueEvent;
use GuzzleHttp\Queue\Queue\ClientQueueInterface;

class QueueSubscriber implements SubscriberInterface, EventTriggerInterface
{

    /** @var  ClientQueueInterface */
    protected $queue;

    /** @var array Array of custom events that are triggered by this class */
    protected $customEventNames = ['enqueue'];

    function __construct(ClientQueueInterface $queue)
    {
        $this->queue = $queue;
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
            'before' => ['beforeListener', 'first']
        ];
    }

    public function beforeListener(BeforeEvent $event)
    {
        // Make a copy of the original request, then detach this interceptor

        // Add reference to the original client
        $this->queue->setClient($event->getClient());

        $transaction  = new Transaction($event->getClient(), $event->getRequest());
        $enqueueEvent = new EnqueueEvent($transaction);

        $event->getRequest()->getEmitter()->emit('enqueue', $enqueueEvent);

        // Remove all event listeners from the original request
        // We don't want to call a completed or error report for a promise
        foreach ($event->getRequest()->getEmitter()->listeners() as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $event->getRequest()->getEmitter()->removeListener($eventName, $listener);
                $event->getClient()->getEmitter()->removeListener($eventName, $listener);
            }
        }
        $event->intercept(new Response(200));

        // Now put it in the queue
        $this->queue->enqueue($event->getRequest());
        return;

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