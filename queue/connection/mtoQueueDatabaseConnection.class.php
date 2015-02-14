<?php
mtoClass :: import("mtokit/queue/connection/mtoQueueAbstractConnection.class.php");
mtoClass :: import("mtokit/db/mtoDb.class.php");

class mtoQueueDatabaseConnection extends mtoQueueAbstractConnection
{
    
    private $table = null;
    
    function connect($args = array())
    {
        //mtoClass :: import("mtokit/db/toolkit/mtoDbTools.class.php");
        //mtoToolkit :: merge(new mtoDbTools());
        $this->table = isset($args['table']) ? $args['table'] : "";
    }
    
    function init($args = array())
    {
        mtoDb :: execute("truncate table " . $this->table);
    }
    
    function pop($args = array())
    {
        $row = mtoDb :: fetchOneRow("select * from " . $this->table . " where name='".$this->queue."' order by id asc limit 1");
        if (!empty($row['id']))
        {
            mtoDb :: execute("delete from " . $this->table . " where id=" . intval($row['id']));
            return unserialize($row['evt']);
        }
        else
        {
            return false;
        }
    }
    
    function push($event = array())
    {
        mtoDb :: execute("insert into " . $this->table . " (name, evt) values ('".$this->queue."', '".serialize($event)."')");
    }
    
    function lock()
    {
        return mtoDb :: fetchOneValue("select get_lock('q_".$this->queue."', 1)");
    }
    
    function unlock()
    {
        mtoDb :: execute("select release_lock('q_".$this->queue."')");
    }
}