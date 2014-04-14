<?php
namespace GuzzleHttp\Queue\Queue;

use GuzzleHttp\ClientInterface;

interface ClientQueueInterface extends QueueInterface
{

    /**
     * @return ClientInterface
     */
    public function getClient();

    public function setClient(ClientInterface $client);
}