<?php
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
mtoClass :: import('mtokit/cache/connection/mtoCacheConnection.interface.php');

abstract class mtoCacheAbstractConnection implements mtoCacheConnection
{

    protected $config;
    protected $conf;
    use mtoSingletone;

    function __construct($args = array())
    {
        $this->conf = mtoConf :: instance();
        foreach ($args as $option_name => $option_value)
        {
            if (!is_null($option_value))
            {
                $this->config[$option_name] = $option_value;
            }
        }
    }

    protected function _resolveKey($keys)
    {
        $suffix = $this->conf->get("core.suffix");
        if (is_array($keys))
        {
            $new_keys = array();
            foreach ($keys as $pos => $key)
            {
                $new_keys[$pos] = $key . "_" . $suffix;
            }
        }
        else
        {
            $new_keys = $keys . "_" . $suffix;
        }

        return $new_keys;
    }

    function get($keys)
    {
        if (!is_array($keys))
        {
            $values = $this->_getSingleKeyValue($this->_resolveKey($keys));
        }
        else
        {
            $resolved_keys = $this->_resolveKey($keys);
            $values = array();
            foreach ($resolved_keys as $key_index => $resolved_key)
            {
                $values[$keys[$key_index]] = $this->_getSingleKeyValue($resolved_key);
            }
        }

        return $values;
    }

    protected function _getLockName($key, $lock_name = 'lock')
    {
        return $key . '_' . $lock_name;
    }

    function lock($key, $ttl = false, $lock_name = 'lock')
    {
        return $this->add($this->_getLockName($key, $lock_name), '1', $ttl);
    }

    function unlock($key, $lock_name = 'lock')
    {
        return $this->delete($this->_getLockName($key, $lock_name));
    }

    function increment($key, $value = 1, $ttl = false)
    {
        if (is_null($current_value = $this->get($key)))
        {
            return false;
        }

        if (!$this->lock($key, 10))
        {
            return false;
        }

        if (is_array($current_value))
        {
            $this->unlock($key);
            throw new Exception("The value can't be a array");
        }

        if (is_object($current_value))
        {
            $this->unlock($key);
            throw new Exception("The value can't be a object");
        }

        $new_value = $current_value + $value;

        $this->set($key, $new_value, $ttl);

        $this->unlock($key);

        return $new_value;
    }

    function decrement($key, $value = 1, $ttl = false)
    {
        if (is_null($current_value = $this->get($key)))
            return false;

        if (!$this->lock($key, 10, '1'))
            return false;

        $new_value = $current_value - $value;

        if ($new_value < 0)
            $new_value = 0;

        $this->set($key, $new_value, $ttl);

        $this->unlock($key, '1');

        return $new_value;
    }

    function safeIncrement($key, $value = 1, $ttl = false)
    {
        if ($result = $this->increment($key, $value))
            return $result;

        $this->add($key, 0, $ttl);

        return $this->increment($key, $value);
    }

    function safeDecrement($key, $value = 1, $ttl = false)
    {
        if ($result = $this->decrement($key, $value))
            return $result;

        $this->add($key, 0, $ttl);

        return $this->decrement($key, $value);
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
