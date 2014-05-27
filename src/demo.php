<?php
include '../vendor/autoload.php';
function loader($className)
{
    $filename = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    $filename = explode(DIRECTORY_SEPARATOR, $filename);

    unset($filename[array_search('GuzzleHttp', $filename)]);

    $filename = join(DIRECTORY_SEPARATOR, $filename);
    if (file_exists($filename)) {
        include_once($filename);
    }
}

spl_autoload_register('loader');
ob_start();
ob_implicit_flush(1);
//--------------------------------------------------------------------------------

$hostnameQueue = new \GuzzleHttp\Queue\Queue\HostnameQueue();
$hostnameQueue->setBatchLimit(5);

$queueSubscriber = new \GuzzleHttp\Queue\QueueSubscriber($hostnameQueue);

$client = new GuzzleHttp\Client();


/** @var \GuzzleHttp\Message\Request[] $requests */

$retrySubscriber = new \GuzzleHttp\Retry\QueuedRetrySubscriber($hostnameQueue, [
    'max' => 5,
    'filter' => function($retry, \GuzzleHttp\Event\AbstractTransferEvent $event){
            return $event->getResponse() && $event->getResponse()->getStatusCode() == 408;
        }
]);

$multipointSubscriber = new \GuzzleHttp\MultipointRequest\MultipointRequestSubscriber($hostnameQueue);

$client->getEmitter()->attach($retrySubscriber);
$client->getEmitter()->attach($queueSubscriber);
$client->getEmitter()->attach($multipointSubscriber);

$client->loadCustomEvents();

$requests = [
    $client->createRequest('get', 'http://responder.dev/?sleep=0&id=1&status=200'),
];

$client->sendAll($requests, [
        'before'   => [
            'fn'   => function (\GuzzleHttp\Event\BeforeEvent $event) use (&$hostnameQueue) {
                    echo "Started request {$event->getRequest()->getUrl()} <br/>";
                    ob_flush();
                },
            'once' => false
        ],
        'error'    => [
            'fn'   => function (\GuzzleHttp\Event\ErrorEvent $event) {
                    echo 'Request failed: ' . $event->getRequest()->getUrl() . '<br/>';
                    ob_flush();
                },
            'once' => false
        ],
        'complete' => [
            'fn'   => function (\GuzzleHttp\Event\CompleteEvent $event) use ($hostnameQueue) {
                    echo 'Completed request to ' . $event->getRequest()->getUrl() . '<br/>';
                    ob_flush();
                },
            'once' => false
        ],
        'enqueue'  => [
            'fn'   => function (\GuzzleHttp\Queue\Event\EnqueueEvent $enqueueEvent, $eventName) {
                    echo "Thrown into queue event {$enqueueEvent->getRequest()->getUrl()}<br/>";
                    ob_flush();
                },
            'once' => false
        ]
    ]
);


echo 'Done';
ob_end_flush();