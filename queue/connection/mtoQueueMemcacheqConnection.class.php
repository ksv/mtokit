<?php
mtoClass :: import("mtokit/queue/connection/mtoQueueAbstractConnection.class.php");

class mtoQueueMemcacheqConnection extends mtoQueueAbstractConnection
{
    
    private $conn;
    
    function connect($args = array())
    {
        $dsn = new mtoUri($args['dsn']);
        $this->conn = new Memcache();
        $this->conn->connect($dsn->getHost(), $dsn->getPort());
    }
    
    function init()
    {
        $this->conn->flush();
    }
    
    function pop()
    {
        return $this->conn->get($this->queue);
    }
    
    function push($event = array())
    {
        $this->conn->set($this->queue, $event);
    }
    
    function lock()
    {
        return true;
    }
    
    function unlock()
    {
        return true;
    }

    function length()
    {
        return 0;
    }
    
}