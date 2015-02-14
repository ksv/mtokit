<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');
mtoClass :: import('mtokit/cache/mtoCacheFactory.class.php');
mtoClass :: import('mtokit/net/mtoUri.class.php');

class mtoCacheTools extends mtoAbstractTools
{

    protected $_cache = array();
    
    function getCache($name = 'default')
    {
        return $this->getCacheByName($name);
    }

    function hasCache($name)
    {
        return array_key_exists($name, $this->_cache);
    }

    function getCacheByName($name)
    {
        if (isset($this->_cache[$name]) && is_object($this->_cache[$name]))
        {
            return $this->_cache[$name];
        }

        $this->_cache[$name] = $this->createCache($name);

        return $this->_cache[$name];
    }

    function createCache($name)
    {
        $dsn = new mtoUri(mtoConf :: instance()->get("cache", $name));
        $args = array();
        $args['type'] = $dsn->getProtocol();
        $args['host'] = $dsn->getHost();
        $args['port'] = $dsn->getPort();
        foreach ($dsn->getQueryItems() as $k => $v)
        {
            $args[$k] = $v;
        }
       
        return mtoCacheFactory :: createCache($args);
    }

    function setCache($name = 'default', $cache)
    {
        $this->_cache[$name] = $cache;
    }
    
    
    
    
    
    function getCacheLogFilename($cache_type, $id)
    {
        $path = "cache/" . $cache_type;
        $path .= "/" . substr($id, 0, 1);
        $path .= "/" . substr($id, 0, 3);
        if (!file_exists("var/log/" . $path))
        {
            mtoFs :: mkdir("var/log/" . $path);
        }
        return $path . "/" . $id;
        
    }
    
    function getCachePathParts($id, $depth = 2)
    {
        $parts = array();
        $hash = md5($id);
        for ($i=1; $i<=$depth; $i++)
        {
            $part = substr($hash, ($i-1)*2, 2);
            if ($part == "ad")
            {
                $part = "bd";
            }
            $parts[] = $part;
        }
        return $parts;
    }
    


}