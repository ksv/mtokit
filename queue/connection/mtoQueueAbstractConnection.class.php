<?php
abstract class mtoQueueAbstractConnection
{
    protected $config;
    protected $queue;
    
    function __construct($queue, $args = array())
    {
        $this->queue = $queue;
        $this->connect($args);
    }
    
    abstract function connect($args = array());
    abstract function init();
    abstract function pop();
    abstract function push($event = array());
    abstract function lock();
    abstract function unlock();
    
    
}