<?php

mtoClass :: import('mtokit/cache/decorator/mtoCacheDecorator.interface.php');
mtoClass :: import('mtokit/cache/connection/mtoCacheAbstractConnection.class.php');

abstract class mtoCacheBaseDecorator extends mtoCacheAbstractConnection implements mtoCacheDecorator
{

    protected $cache;

    function __construct($cache)
    {
        $this->cache = $cache;
    }

    function getCache()
    {
        return $this->cache;
    }

    function __call($method, $args)
    {
        if (!is_callable(array($this->cache, $method)))
            throw new Exception('Decorated cache driver does not support method "' . $method . '"');

        return call_user_func_array(array($this->cache, $method), $args);
    }

    function getType()
    {
        return $this->cache->getType();
    }

}