<?php

mtoClass :: import('mtokit/cache/decorator/mtoCacheBaseDecorator.class.php');

class mtoCacheMintDecorator extends mtoCacheBaseDecorator
{

    protected $fake_ttl;
    protected $default_ttl;
    protected $cooled_ttl;

    function __construct($cache, $default_ttl = 300, $fake_ttl = 86400, $cooled_ttl = 60)
    {
        parent::__construct($cache);
        $this->fake_ttl = $fake_ttl;
        $this->default_ttl = $default_ttl;
        $this->cooled_ttl = $cooled_ttl;
    }

    function add($key, $value, $ttl = false, $args = array())
    {
        return $this->cache->add($key, $this->_getRealValue($value, $ttl), $this->fake_ttl);
    }

    function set($key, $value, $ttl = false, $args = array())
    {
        return $this->cache->set($key, $this->_getRealValue($value, $ttl), $this->fake_ttl);
    }

    function replace($key, $value, $ttl = false, $args = array())
    {
        return $this->cache->replace($key, $this->_getRealValue($value, $ttl), $this->fake_ttl);
    }

    function coolDownKey($key)
    {
        $real_value = $this->cache->get($key);

        if (!$real_value || !is_array($real_value))
        {
            return;
        }

        list($value, $expire_time) = $real_value;

        // "-1" is a second before now. Means next time anyone gets this cached item it should receive null and so to refresh cached item
        $this->cache->set($key, $this->_getRealValue($value, -1), $this->cooled_ttl);
    }

    protected function _getRealValue($value, $ttl)
    {
        if (!$ttl)
        {
            $ttl = $this->default_ttl;
        }

        $expire_time = time() + $ttl;
        return array($value, $expire_time);
    }

    protected function _extractRealValue($key, $real_value)
    {
        if (!$real_value)
        {
            return NULL;
        }

        list($value, $expire_time) = $real_value;

        if ($expire_time > time())
        {
            return $value;
        }
        else
        {
            // now we refresh ttl for this item and return null. We hope that controller will refresh the cached item in this case.
            // $this->cooled_ttl seconds should be enough for any process to refresh cached item.
            $this->cache->set($key, $this->_getRealValue($value, $this->cooled_ttl), $this->cooled_ttl);
            return NULL;
        }
    }

    function get($keys, $args = array())
    {
        $real_values = $this->cache->get($keys);
        if (!$real_values || !is_array($real_values))
        {
            return null;
        }

        if (!is_array($keys))
        {
            return $this->_extractRealValue($keys, $real_values);
        }

        $result = array();
        foreach ($real_values as $key => $real_value)
        {
            $result[$key] = $this->_extractRealValue($key, $real_value);
        }

        return $result;
    }

    function delete($key)
    {
        return $this->cache->delete($key);
    }

    function flush()
    {
        $this->cache->flush();
    }

    function dumpKeys($args = array())
    {
        return $this->cache->dumpKeys($args);
    }

}

