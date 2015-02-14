<?php

mtoClass :: import('mtokit/cache/connection/mtoCacheAbstractConnection.class.php');

class mtoCacheMemcacheConnection extends mtoCacheAbstractConnection
{

    static $_connected_servers;
    public $flush_pause = 1;
    protected $_server_id;
    private $fetched = array();

    function __construct($args = array())
    {
        parent::__construct($args);

        $this->_server_id = $this->config['host'] . ':' . $this->config['port'];
    }

    function getType()
    {
        return 'memcache';
    }

    protected function _getMemcache()
    {
        if (!self::$_connected_servers[$this->_server_id])
        {
            $server = new Memcache();
            //if (!$server->addServer($this->config['host'], $this->config['port']))
            if (!$server->pconnect($this->config['host'], $this->config['port']))
            {
                throw new Exception("Can't connect to memcache");
            }

            self::$_connected_servers[$this->_server_id] = $server;
        }
        return self::$_connected_servers[$this->_server_id];
    }

    function add($key, $value, $ttl = false)
    {
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " ADD " . json_encode($key), "debug/memcache");
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        $v = $this->_getMemcache()->add($this->_resolveKey($key), $value, false, (int) $ttl);
        mtoProfiler :: instance()->timerStop("cachet");
        //mtoProfiler :: instance()->logDebug(json_encode($this->_resolveKey($key)) . "\t" .  json_encode($value), "debug/cache_add");
        return $v;
    }

    function set($key, $value, $ttl = false)
    {
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            mtoProfiler :: instance()->logDebug(" SET " . $key . "(" . number_format(memory_get_usage()) . ") " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''), "debug/memcache");
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        $k = $this->_resolveKey($key);
        $v = $this->_getMemcache()->set($k, $value, false, (int) $ttl);
        if ($v === false)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\t" . $k, "debug/memcache_set");
        }
        $this->fetched[$k] = $value;
        mtoProfiler :: instance()->timerStop("cachet");
        //mtoProfiler :: instance()->logDebug(json_encode($this->_resolveKey($key)) . "\t" .  json_encode($value), "debug/cache_set");
        return $v;
    }

    function replace($key, $value, $ttl = false)
    {
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " REP " . $key, "debug/memcache");
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        $v = $this->_getMemcache()->replace($this->_resolveKey($key), $value, false, (int) $ttl);
        mtoProfiler :: instance()->timerStop("cachet");
        return $v;
    }

    function get($key)
    {
        $ts = microtime(true);
        $k = $this->_resolveKey($key);
        if (!is_array($key))
        {
            if (isset($this->fetched[$k]))
            {
                return $this->fetched[$k];
            }
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        mtoProfiler :: instance()->timerStartPinba("mcg", array('scope' => "memcache", 'operation' => "get", 'suffix' => mtoConf :: instance()->get("core", "suffix"), 'fullop' => "memcache::get"));
        $value = $this->_getMemcache()->get($k);
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            //mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " GET[".round(microtime(true) - $ts, 3)."] " . json_encode($k), "debug/memcache");
        }
        if (!is_array($key))
        {
            $this->fetched[$k] = $value;
        }

        if (false === $value)
        {
            mtoProfiler :: instance()->timerStopPinba("mcg");
            //mtoProfiler :: instance()->logDebug("MTO-MISS:" . json_encode($k), "debug/cache_miss");
            mtoProfiler :: instance()->timerStop("cachet");
            return NULL;
        }
        $suffix = mtoConf :: instance()->get("core", "suffix");

        if (is_array($k))
        {
            $new_value = array();
            foreach ($k as $one_key)
            {
                $new_key = preg_replace("#_".$suffix."$#", "", $one_key);
                if (isset($value[$one_key]))
                {
                    $new_value[$new_key] = $value[$one_key];
                    $this->fetched[$one_key] = $value[$one_key];
                }
                else
                {
                    $new_value[$new_key] = null;
                }
            }
            $value = $new_value;
        }

//        $msg = array(
//            "",
//            "KEY: " . json_encode($k),
//            "URI: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "-"),
//            //"VALUE: " . json_encode($value),
//            "SIZE: " . strlen(json_encode($value)),
//            "-------------",
//            "-------------"
//        );
//        mtoProfiler :: instance()->logDebug(implode("\n", $msg), "debug/cache_get");
        mtoProfiler :: instance()->timerStopPinba("mcg");
        mtoProfiler :: instance()->timerStop("cachet");
        return $value;
    }

    function delete($key, $ttl = 0)
    {
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " DEL " . $key, "debug/memcache");
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        $k = $this->_resolveKey($key);
        $v = $this->_getMemcache()->delete($k, $ttl);
        unset($this->fetched[$k]);
        $k = preg_replace("#_pd$#", "_stg", $k);
        $v = $this->_getMemcache()->delete($k, $ttl);
        //mtoProfiler :: instance()->logDebug($this->_resolveKey($key), "debug/cache_del");
        mtoProfiler :: instance()->timerStop("cachet");
        return $v;
    }

    function increment($key, $value = 1, $ttl = false)
    {
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " INC " . $key, "debug/memcache");
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        $v = $this->_getMemcache()->increment($this->_resolveKey($key), $value, $ttl);
        //mtoProfiler :: instance()->logDebug($this->_resolveKey($key) . "\t" .  json_encode($value) . "\t" . $v, "debug/cache_inc");
        mtoProfiler :: instance()->timerStop("cachet");
        return $v;
    }

    function decrement($key, $value = 1, $ttl = false)
    {
        if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " DEC " . $key, "debug/memcache");
        }
        mtoProfiler :: instance()->timerStartCount("cachec");
        mtoProfiler :: instance()->timerStartInc("cachet");
        $v = $this->_getMemcache()->decrement($this->_resolveKey($key), $value, $ttl);
        mtoProfiler :: instance()->timerStop("cachet");
        return $v;
    }

    function flush()
    {
        $this->_getMemcache()->flush();
        usleep($this->flush_pause);
    }

    function status()
    {
        return $this->_getMemcache()->getStats();
    }

    function dumpKeys($args = array())
    {
        //$c = new Memcache();
        //$c->connect($this->config['host'], $this->config['port']);
        $slabs = $this->_getMemcache()->getStats("slabs");
        //$slabs = $c->getStats("slabs");
        $keys = array();
        foreach ($slabs as $slabid => $slab)
        {
    	    if (!is_numeric($slabid))
    	    {
                continue;
    	    }
            $content = $this->_getMemcache()->getStats("cachedump", intval($slabid), 1000000);
            //$content = $c->getStats("cachedump", intval($slabid), 1000000);
            if (is_array($content))
            {
                foreach ($content as $key => $info)
                {
                    if (isset($args['pattern']))
                    {
                        if (!preg_match("#" . $args['pattern'] . "#", $key))
                        {
                            continue;
                        }
                    }
                    if (isset($args['use_suffix']))
                    {
                        if (!preg_match("#_".mtoConf :: instance()->val("core|suffix")."$#", $key))
                        {
                            continue;
                        }
                    }
                    $keys[] = $key;
                }
            }
        }
        if (isset($args['sort']))
        {
            sort($keys);
        }
        return $keys;
    }

}
