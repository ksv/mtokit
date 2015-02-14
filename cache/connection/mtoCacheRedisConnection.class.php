<?php

mtoClass :: import("mtokit/cache/lib/redis/Rediska.php");
mtoClass :: import("mtokit/cache/lib/redis/Rediska/Key.php");
mtoClass :: import('mtokit/cache/connection/mtoCacheAbstractConnection.class.php');

class mtoCacheRedisConnection extends mtoCacheAbstractConnection
{
    protected $connection;


    function __construct($config)
    {        
        $redis_options = array(
                        'servers'   => array(
                            array('host' => $config['host'], 'port' => $config['port']),
                            )
                        );
        
        $this->connection = new Rediska($redis_options);
    }

    function get($key, $args = array())
    {
        $keyObj = new Rediska_Key($key);
        if (mtoConf :: instance()->get("cache_args", "logging"))
        {
            if (is_array($key))
            {
                //mtoProfiler :: instance()->logDebug("GET: " . print_r($key, true), "redis");
            }
            else
            {
                mtoProfiler :: instance()->logDebug("GET: " . $key, "redis");
            }
        }
        return $keyObj->getValue();
    }

    function set($key, $value, $args=array())
    {
        $keyObj = new Rediska_Key($key);
        if (mtoConf :: instance()->get("cache_args", "logging"))
        {
            mtoProfiler :: instance()->logDebug("SET: " . $key, "redis");
        }
        return $keyObj->setValue($value);
    }

    function add($key, $value, $args = array())
    {
        //not implemented
    }

    function delete($key, $args = array())
    {
        $keyObj = new Rediska_Key($key);
        if (mtoConf :: instance()->get("cache_args", "logging"))
        {
            mtoProfiler :: instance()->logDebug("DEL: " . $key, "redis");
        }
        return $keyObj->delete();
    }

    function flush($args = array())
    {
        $this->connection->FlushDb();
    }

    function replace($key, $value, $args = array())
    {
        $this->connection->replace($key, $value);
    }

    function status()
    {
        return $this->connection->info();
    }
    function increment($key, $args = array())
    {
        if($result = $this->connection->increment($key, 1))
        {
            return $result;
        }

        $this->connection->add($key, 0);

        return $this->connection->increment($key, 1);
   }

   function getType()
   {
       return "redis";
   }

}