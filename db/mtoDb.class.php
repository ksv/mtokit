<?php
mtoClass :: import('mtokit/net/mtoUri.class.php');


class mtoDb
{
    
    static function execute($query, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->execute($query);
    }
    
    static function fetch($query, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->fetch($query);
    }
    
    static function fetchOneRow($query, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->fetchOneRow($query);
    }
    
    static function fetchOneValue($query, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->fetchOneValue($query);
    }
    
    static function insert($table, $values, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->insert($table, $values);
    }
    
    static function replace($table, $values, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->replace($table, $values);
    }
    
    static function update($table, $data, $pk, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->update($table, $data, $pk);
    }
    
    static function tableInfo($table_name, $conn = null)
    {
        if (is_null($conn))
        {
            $conn = mtoToolkit :: instance()->getDbConnection();
        }
        return $conn->tableInfo($table_name);
    }
}