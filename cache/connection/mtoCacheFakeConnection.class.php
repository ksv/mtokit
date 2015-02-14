<?php

mtoClass :: import('mtokit/cache/connection/mtoCacheAbstractConnection.class.php');

class mtoCacheFakeConnection extends mtoCacheAbstractConnection
{

    function getType()
    {
        return 'fake';
    }

    function add($key, $value, $ttl = false)
    {
        return true;
    }

    function set($key, $value, $ttl = false)
    {
        return true;
    }

    function replace($key, $value, $ttl = false)
    {
        return true;
    }

    function increment($key, $value = 1)
    {
        return false;
    }

    function get($key)
    {
        return NULL;
    }

    function delete($key)
    {
        return true;
    }

    function flush()
    {
        
    }

    function status()
    {
        return array();
    }

    function dumpKeys($args = array())
    {
        return array();
    }

}
