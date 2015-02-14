<?php
class mtoDbDbalConnection
{
    
    function __construct($dsn_string)
    {
    }
    
    function fetch($query, $bind_array = array())
    {
        return lmbDBAL :: fetch($query);
    }
    
    function fetchByKey($key, $query, $bind_array = array())
    {
        return null;
    }
    
    function fetchOneRow($query, $bind_array = array())
    {
        return lmbDBAL :: fetchOneRow($query);
    }
    
    function fetchOneValue($query, $bind_array = array())
    {
        return lmbDBAL :: fetchOneValue($query);
    }
    
    function execute($query, $bind_array = array())
    {
        return lmbDBAL :: execute($query);
    }
    
    function nextId()
    {
        return 0;
    }
    
    function insert($table, $values)
    {
        return false;
    }
    
    function replace($table, $values)
    {
        return false;
    }
    
    function update($table, $data, $pk)
    {
        return false;
    }
    
    function escape($value)
    {
        return value;
    }
    
    
}