<?php
mtoClass :: import("mtokit/db/lib/sql_db.class.php");
class mtoDbLegacyConnection
{
    protected $conn;
    
    function __construct($dsn_string)
    {
        list($dsn, $login, $password, $params) = explode("|", $dsn_string);
        $dsn = str_replace("mysql:", "", $dsn);
        
        $args = array();
        $parts = explode(";", $dsn);
        foreach ($parts as $part)
        {
            list($key, $value) = explode("=", $part);
            $args[$key] = $value;
        }
        $host = $args['host'];
        if (!empty($args['port']))
        {
            $host .= ":" . $args['port'];
        }
        $this->conn = new sql_db($host, $login, $password, $args['dbname']);
        $this->conn->sql_query("set names utf8");
    }
    
    function fetch($query, $bind_array = array())
    {
        return $this->conn->sql_getall($query, $bind_array);
    }
    
    function fetchByKey($key, $query, $bind_array = array())
    {
        return $this->conn->sql_keygetall($key, $query, $bind_array);
    }
    
    function fetchOneRow($query, $bind_array = array())
    {
        return $this->conn->sql_getone($query, $bind_array);
    }
    
    function fecthOneValue($query, $bind_array = array())
    {
        
    }
    
    function execute($query, $bind_array = array())
    {
        return $this->sql_query($query, $bind_array);
    }
    
    function nextId()
    {
        return $this->conn->sql_nextid();
    }
    
    function insert($table, $values)
    {
        return $this->conn->sql_insert($table, $values);
    }
    
    function replace($table, $values)
    {
        return $this->conn->sql_replace($table, $values);
    }
    
    function update($table, $data, $pk)
    {
        return $this->sql_update($table, $data, $pk);
    }
    
    function escape($value)
    {
        return $this->conn->escape($value);
    }
    
    function sql_getall($query, $bind_array = array())
    {
        return $this->conn->sql_getall($query, $bind_array);
    }
    
    function sql_keygetall($key, $query, $bind_array = array())
    {
        return $this->conn->sql_keygetall($key, $query, $bind_array);
    }
    
    function sql_getone($query, $bind_array = array())
    {
        return $this->conn->sql_getone($query, $bind_array);
    }
    
    function sql_query($query, $bind_array = array())
    {
        return $this->conn->sql_query($query, $bind_array);
    }
    
    function sql_fetchrow($res = 0)
    {
        return $this->conn->sql_fetchrow($res);
    }
    
    function sql_fetchrowset($res = 0)
    {
        return $this->conn->sql_fetchrowset($res);
    }
    
    function sql_affectedrows()
    {
        return $this->conn->sql_affectedrows();
    }
    
    function sql_numrows($res = 0)
    {
        return $this->conn->sql_numrows($res);
    }
    
    function sql_nextid()
    {
        return $this->conn->sql_nextid();
    }
    
    function sql_insert($table, $values, $replace = false)
    {
        return $this->conn->sql_insert($table, $values, $replace);
    }

    function sql_replace($table, $values)
    {
        return $this->conn->sql_replace($table, $values);
    }

    public function sql_update($table, $data, $pk)
    {
        return $this->conn->sql_update($table, $data, $pk);
    }
    
    public function sql_getvalue($table, $key_field, $value_field, $key_value, $where = "")
    {
        return $this->conn->sql_getvalue($table, $key_field, $value_field, $key_value, $where);
    }
    
    public function sql_inspect_table($table_name)
    {
        return $this->conn->sql_inspect_table($table_name);
    }
    
    public function sql_tableinfo($table)
    {
        return $this->conn->sql_tableinfo($table);
    }
    
}