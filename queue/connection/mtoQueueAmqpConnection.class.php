<?php
mtoClass :: import("mtokit/queue/connection/mtoQueueAbstractConnection.class.php");

class mtoQueueAmqpConnection extends mtoQueueAbstractConnection
{
    
    function connect($args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function init($args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function pop()
    {
        throw new mtoException("not implemented");
    }
    
    function push($event = array())
    {
        throw new mtoException("not implemented");
    }
    
    function lock()
    {
        throw new mtoException("not implemented");
    }
    
    function unlock()
    {
        throw new mtoException("not implemented");
    }
    
}