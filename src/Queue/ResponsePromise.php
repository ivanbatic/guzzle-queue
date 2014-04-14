<?php

/**
 * Created by PhpStorm.
 * User: ivan
 * Date: 4/4/14
 * Time: 2:13 PM
 */
namespace GuzzleHttp\Queue;

use GuzzleHttp\Event\AbstractEvent;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Queue\Exception\QueueException;
use GuzzleHttp\Stream\StreamInterface;

class ResponsePromise extends Response
{
    const PROMISE_STATUS_CODE = 195;

    // Request states
    const REQUEST_STATE_NON_EXISTING = 0;
    const REQUEST_STATE_IDLE         = 1;
    const REQUEST_STATE_INITIALIZED  = 2;
    const REQUEST_STATE_HEADERS_SENT = 3;
    const REQUEST_STATE_COMPLETED    = 4;
    const REQUEST_STATE_ERROR        = 5;

    protected $requestState = 0;

    /** @var  Request */
    protected $request;

    protected $responseReady = false;

    protected $eventCallbacks = [
        'before'   => [],
        'complete' => [],
        'error'    => []
    ];

    public function __construct(
        array $headers = [],
        StreamInterface $body = null,
        array $options = []
    ) {
        parent::__construct(self::PROMISE_STATUS_CODE, $headers, $body, $options);
    }

    public function registerEventCallback($eventName, callable $callback)
    {
        $isValidEvent = array_key_exists($eventName, $this->eventCallbacks);
        if (!$isValidEvent) {
            throw new QueueException("Cannot register an event callback for event `$eventName`.");
        }

        $this->eventCallbacks[$eventName][] = $callback;

        return $this;
    }

    /**
     * @param \GuzzleHttp\Message\Request $originalRequest
     */
    public function setRequest(Request $originalRequest)
    {
        $this->request      = $originalRequest;
        $this->requestState = self::REQUEST_STATE_IDLE;

        // Bind events to change the request state
        foreach (['before', 'headers', 'error', 'complete', 'progress'] as $eventName) {
            $this->request->getEmitter()->on($eventName, [$this, 'markEventState']);
        }

        return $this;
    }

    /**
     * @param AbstractEvent $event
     * @param               $eventName
     */
    public function markEventState(AbstractEvent $event, $eventName)
    {
        switch ($eventName) {
            case 'before':
                $this->requestState = self::REQUEST_STATE_INITIALIZED;
                break;
            case 'headers':
                $this->requestState = self::REQUEST_STATE_HEADERS_SENT;
                break;
            case 'error':
                $this->requestState = self::REQUEST_STATE_ERROR;
                break;
            case 'complete':
                $this->requestState = self::REQUEST_STATE_COMPLETED;
                break;
        }
    }

    /**
     * @return \GuzzleHttp\Message\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    public function getRequestState()
    {
        return $this->requestState;
    }

    public function isCompleted()
    {
        return $this->requestState == self::REQUEST_STATE_COMPLETED
        || $this->requestState == self::REQUEST_STATE_ERROR;
    }

}