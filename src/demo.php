<?php
include '../vendor/autoload.php';
function loader($className)
{
    $filename = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    if (file_exists($filename)) {
        include_once($filename);
    }
}

spl_autoload_register('loader');
ob_start();
ob_implicit_flush(1);
//--------------------------------------------------------------------------------

$hostnameQueue = new \GuzzleHttp\Queue\Queue\HostnameQueue();
$hostnameQueue->setBatchLimit(3);

$queueSubscriber = new \GuzzleHttp\Queue\QueueSubscriber($hostnameQueue);

$client = new GuzzleHttp\Client();
$client->getEmitter()->attach($queueSubscriber);
$client->loadCustomEvents();

/** @var \GuzzleHttp\Message\Request[] $requests */
$requests = [
    $client->createRequest('get', 'http://responder.dev/?sleep=3&id=1'),
    $client->createRequest('get', 'http://responder.dev/?sleep=1&id=2'),
    $client->createRequest('get', 'http://responder.dev/?sleep=1&id=3'),
    $client->createRequest('get', 'http://responder.dev/?sleep=1&id=4'),
    $client->createRequest('get', 'http://responder.dev/?sleep=1&id=5'),
    $client->createRequest('get', 'http://responder-1.dev/?sleep=3&id=1'),
    $client->createRequest('get', 'http://responder-1.dev/?sleep=1&id=2'),
    $client->createRequest('get', 'http://responder-1.dev/?sleep=1&id=3'),
    $client->createRequest('get', 'http://responder-1.dev/?sleep=1&id=4'),
    $client->createRequest('get', 'http://responder-2.dev/?sleep=3&id=1'),
    $client->createRequest('get', 'http://responder-2.dev/?sleep=1&id=2'),
    $client->createRequest('get', 'http://responder-2.dev/?sleep=1&id=3'),
    $client->createRequest('get', 'http://responder-2.dev/?sleep=1&id=4'),
    $client->createRequest('get', 'http://responder-2.dev/?sleep=1&id=5'),
];


$client->sendAll($requests, [
        'before'   => function (\GuzzleHttp\Event\BeforeEvent $event) {
                echo "Started request to {$event->getRequest()->getUrl()} <br/>";
                ob_flush();
            },
        'complete' => function (\GuzzleHttp\Event\CompleteEvent $event) {
                echo 'Finish request to ' . $event->getRequest()->getUrl() . "<br/>";
                ob_flush();
            },
        'enqueue'  => function (\GuzzleHttp\Queue\Event\EnqueueEvent $enqueueEvent, $eventName) {
//                echo 'Thrown into queue event<br/>';
                ob_flush();
            },
        'error'    => function (\GuzzleHttp\Event\ErrorEvent $event) {
                echo 'Request failed: ' . $event->getRequest()->getUrl() . "<br/>";
                ob_flush();
            }
    ]
);
echo 'Done';
ob_end_flush();