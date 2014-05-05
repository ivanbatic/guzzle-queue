<?php

namespace GuzzleHttp\Queue;


use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\EventTriggerInterface;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Queue\Event\EnqueueEvent;
use GuzzleHttp\Queue\Queue\ClientQueueInterface;

class QueueSubscriber implements SubscriberInterface, EventTriggerInterface
{

    /** @var  ClientQueueInterface */
    protected $queue;

    /** @var array */
    protected $requestListeners = [];

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
            'before'   => ['beforeListener', 'first'],
        ];
    }

    public function beforeListener(BeforeEvent $event)
    {
        // Remove this listener
        $event->getRequest()->getEmitter()->removeListener('before', [$this, 'beforeListener']);

        // Add reference to the original client
        $this->queue->setClient($event->getClient());

        // Create and emit enqueue event
        $transaction  = new Transaction($event->getClient(), $event->getRequest());
        $enqueueEvent = new EnqueueEvent($transaction);

        // Emit the Enqueue event
        $event->getRequest()->getEmitter()->emit('enqueue', $enqueueEvent);

        // Strip all events so they are not emitted on interception
        $this->requestListeners = $event->getRequest()->getEmitter()->getListeners();
        $event->getRequest()->getEmitter()->setListeners([]);

        $event->intercept(new ResponsePromise());

        // Reattach events
        $event->getRequest()->getEmitter()->setListeners($this->requestListeners);

        // Put the request into the queue
        $this->queue->enqueue($event->getRequest());
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