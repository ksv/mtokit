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


}