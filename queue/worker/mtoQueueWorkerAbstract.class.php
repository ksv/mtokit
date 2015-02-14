<?php
abstract class mtoQueueWorkerAbstract
{
    protected $queues = array();
    protected $parent;
    
    
    function getSupportedQueues()
    {
        return $this->queues;
    }
    
    function setParent($p)
    {
        $this->parent = $p;
    }
    
    function getParent()
    {
        return $this->parent;
    }
    
    
    abstract function process($args = array());
    abstract function commit();
    
}