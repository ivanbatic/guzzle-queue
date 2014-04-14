<?php
/**
 * Created by PhpStorm.
 * User: ivan
 * Date: 4/4/14
 * Time: 3:20 PM
 */

namespace GuzzleHttp\Queue\Queue;


use GuzzleHttp\Message\RequestInterface;

interface QueueInterface extends \Countable
{

    /**
     * @param RequestInterface $request
     */
    public function enqueue(RequestInterface $request);

    /**
     * @param RequestInterface $request
     */
    public function dequeue(RequestInterface $request);

}