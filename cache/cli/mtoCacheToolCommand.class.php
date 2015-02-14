<?php

class mtoCacheToolCommand extends mtoCliBaseCommand
{
    protected $conn = null;
    protected $toolkit = null;

    function execute($args = array())
    {
        if (!isset($args['conn']))
        {
            throw new mtoCliException("Connection not defined");
        }
        if (empty($args['type']))
        {
            throw new mtoCliException("Operation not defined");
        }
        $method = "cmd" . mto_camel_case($args['type']);
        if (!method_exists($this, $method))
        {
            throw new mtoCliException("Operation not exists");
        }
        $this->toolkit = mtoToolkit :: instance();
        $this->conn = $this->toolkit->getCache($args['conn']);

        $this->$method($args);
    }

    protected function cmdDeleteTag($args = array())
    {
        if (empty($args['tag']))
        {
            throw new mtoCliException("Tag not defined");
        }
        $this->conn->deleteByTag($args['tag']);
        $this->out("Removed");
    }

    protected function cmdFlush($args = array())
    {
        $this->conn->flush();
        $this->out("Flushed");
    }

    protected function cmdDeleteKey($args = array())
    {
        if (empty($args['key']))
        {
            throw new mtoCliException("Key not defined");
        }
        $this->conn->delete($args['key']);
        $this->out("Deleted");
    }

    protected function cmdGet($args = array())
    {
        if (empty($args['key']))
        {
            throw new mtoCliException("Key not defined");
        }
        $val = $this->conn->get($args['key']);
        $this->out("VALUE:");
        $this->out($val);
    }

    protected function cmdDump($args = array())
    {
        $keys = $this->conn->dumpKeys($args);
        $this->out("Cache dump:");
        $this->out($keys);
        $this->out("================");
        $this->out(count($keys) . " dumped");
    }

    function infoTitle()
    {
        return "Cache utilities";
    }

    function infoDescription()
    {
        return "Manual operations with cache such different types of cache flushing, dumping, ect...";
    }

    function infoArguments()
    {
        return array(
            array('mapto' => "type", "description" => "Cache operation(delete_tag|flush|delete_key|get|dump)")
        );
    }

    function infoOptions()
    {
        return array(
            array('name' => "conn", 'required' => true, 'default' => "memcache", 'description' => "Connection name (default to memcache)"),
            array('name' => "tag", 'description' => "Required by delete_by_tag"),
            array('name' => "key", 'description' => "Required by delete_by_key and get"),
            array('name' => "pattern", 'description' => "Used for dump")
        );
    }
}