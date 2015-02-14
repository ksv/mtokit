<?php

class mtoCacheFactory
{

    static function createCache($args)
    {
        $class = "mtoCache" . ucfirst($args['type']) . 'Connection';
        if (empty($args['type']) || !file_exists(__DIR__ . "/connection/" . $class . ".class.php"))
        {
            $req = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "none";
            $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "none";
            mtoProfiler :: instance()->logDebug($req . "\t" . $ref . "\t" . print_r($args, true), "debug/create_cache");
        }
        
        mtoClass::import("mtokit/cache/connection/" . $class . ".class.php");
        $connection = new $class($args);

        if (isset($args['mint']) && $args['mint'])
        {
            mtoClass::import("mtokit/cache/decorator/mtoCacheMintDecorator.class.php");
            $connection = new mtoCacheMintDecorator($connection);
        }

        if (isset($args['tags']) && $args['tags'])
        {
            mtoClass::import("mtokit/cache/decorator/mtoCacheTagDecorator.class.php");
            $connection = new mtoCacheTagDecorator($connection);
        }

        return $connection;
    }

    static protected function getWrappers($dsn)
    {
        if (!$wrappers = $dsn->getQueryItem('wrapper'))
            return array();

        if (!is_array($wrappers))
            $wrappers = array($wrappers);

        return $wrappers;
    }

    static protected function getConnectionClass($dsn)
    {
        $driver = $dsn->getProtocol();

        $class = 'lmbCache' . ucfirst($driver) . 'Connection';
        if (!class_exists($class))
        {
            $file = DIRNAME(__FILE__) . '/drivers/' . $class . '.class.php';
            if (!file_exists($file))
                throw new lmbException("Cache driver '$driver' file not found for DSN '" . $dsn->toString() . "'!");

            lmb_require($file);
        }

        return $class;
    }

    static protected function applyWrapper($connection, $wrapper_name)
    {
        $wrapper_class = 'lmb' . ucfirst($wrapper_name) . 'CacheWrapper';
        if (!class_exists($wrapper_class))
        {
            $file = DIRNAME(__FILE__) . '/wrappers/' . $wrapper_class . '.class.php';
            if (!file_exists($file))
                throw new lmbException(
                        "Cache wripper '$wrapper_class' file not found",
                        array(
                            'dsn' => $dsn,
                            'name' => $wrapper_name,
                            'class' => $wrapper_class,
                            'file' => $file,
                        )
                );

            lmb_require($file);
        }
        return new $wrapper_class($connection);
    }

    static function createLoggedConnection($dsn, $name)
    {
        return new lmbLoggedCache(self::createConnection($dsn), $name);
    }

}