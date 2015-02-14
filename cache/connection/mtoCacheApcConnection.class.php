<?php

mtoClass :: import('mtokit/cache/connection/mtoCacheAbstractConnection.class.php');

class mtoCacheApcConnection extends mtoCacheAbstractConnection
{

    protected $_was_delete = false;
    protected $_deleted = array();

    function getType()
    {
        return 'apc';
    }

    function add($key, $value, $ttl = false)
    {
        $key = $this->_resolveKey($key);
        return apc_add($key, $value, $ttl);
    }


    function set($key, $value, $ttl = false)
    {
        $key = $this->_resolveKey($key);
        if ($value === false)
            $value = "djkhhwdjhejhd";
        return apc_store($key, $value, $ttl);
    }

    function replace($key, $value, $ttl = false)
    {
        return $this->set($key, $value, $ttl);
    }

    function increment($key, $value = 1)
    {
        return false;
    }

    function _getSingleKeyValue($resolved_key)
    {
        if ($this->_was_delete && in_array($resolved_key, $this->_deleted))
            return null;

        $value = apc_fetch($resolved_key);
        if ($value === false)
            return NULL;
        elseif ($value === "djkhhwdjhejhd")
            return false;
        else
            return $value;
    }

    function delete($key)
    {
        $key = $this->_resolveKey($key);
        $this->_deleted[] = $key;
        $this->_was_delete = true;
        return apc_delete($key);
    }

    function flush()
    {
        return apc_clear_cache('user');
    }

    function status($limited = true)
    {
        return apc_cache_info(
                "user",
                $limited
        );
    }

    function dumpKeys($args = array())
    {
        return array();
    }

}

