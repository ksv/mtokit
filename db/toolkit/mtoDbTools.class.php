<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');
mtoClass :: import("mtokit/db/mtoDbConnection.class.php");
mtoClass :: import("mtokit/db/mtoDbLegacyConnection.class.php");
mtoClass :: import("mtokit/db/mtoDbDbalConnection.class.php");
mtoClass :: import("mtokit/db/mtoDbMongoConnection.class.php");
mtoClass :: import("mtokit/db/mtoDb.class.php");


class mtoDbTools extends mtoAbstractTools
{

    protected $_connections = array();
    
    function getDbConnection($name = "default")
    {
        $conf = mtoConf :: instance();
        $dsn = $conf->get("db", "dsn_" . $name);
        list($driver, $etc) = explode(":", $dsn, 2);
        if (!isset($this->_connections[$name]))
        {
            if ($conf->get("db", "use_pdo"))
            {
                if ($driver == "mongodb")
                {
                    $this->_connections[$name] = new mtoDbMongoConnection($dsn);
                }
                else
                {
                    $this->_connections[$name] = new mtoDbConnection($dsn);
                }
            }
            elseif ($conf->get("db", "use_dbal"))
            {
                $this->_connections[$name] = new mtoDbDbalConnection($dsn);
            }
            else
            {
                $this->_connections[$name] = new mtoDbLegacyConnection($dsn);
            }
        }
        return $this->_connections[$name];
    }


}