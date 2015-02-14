<?php
mtoClass :: import("mtokit/db/mtoDb.class.php");

class mtoDbConnection
{
    
    protected $conn;
    protected $stmt;
    protected $qcount = 0;
    
    function __construct($dsn_string)
    {
        list($dsn, $login, $password, $params) = explode("|", $dsn_string);
        $connect_args = array();
        if (!empty($params))
        {
            $parts = explode(";", $params);
            foreach ($parts as $part)
            {
                list($k, $v) = explode("=", $part);
                $connect_args[constant('PDO::'.$k)] = constant('PDO::' . $v);
            }
        }
        try 
        {
            $this->conn = new PDO($dsn, $login, $password, $connect_args);
            $this->conn->setAttribute(PDO :: ATTR_ERRMODE, PDO :: ERRMODE_EXCEPTION);
            //$this->conn->setAttribute(PDO :: ATTR_EMULATE_PREPARES, 0);
            $this->execute("set names /*{$dsn_string}*/ utf8");
            $this->execute("set sql_mode=''");
        }
        catch (PDOException $e)
        {
            mtoProfiler :: instance()->logDebug($e->getMessage(), "query");
        }
    }
    
    function fetch($query, $bind_array = array(), $args = array())
    {
        $this->execute($query, $bind_array, $args);
        mtoProfiler :: instance()->timerStartInc("dbf");
        if (isset($args['buffered']))
        {
            $data = array();
            $index = 1;
            while ($row = $this->stmt->fetch(PDO :: FETCH_ASSOC))
            {
                $data[] = $row;
                $index++;
                if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
                {
                    var_dump(date("Y-m-d H:i:s") . " ::: $index rows fetched");
                }
            }
            mtoProfiler :: instance()->timerStop("dbf");
            return $data;
        }
        else
        {
            $v = $this->stmt->fetchAll(PDO :: FETCH_ASSOC);
            mtoProfiler :: instance()->timerStop("dbf");
            return $v;
        }
    }
    
    function fetchByKey($key, $query, $bind_array = array(), $args = array())
    {
        if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
        {
            var_dump(date("Y-m-d H:i:s") . " ::: start fetch");
        }
        $rs = $this->fetch($query, $bind_array, $args);
        if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
        {
            var_dump(date("Y-m-d H:i:s") . " ::: end fetch");
        }
        if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
        {
            var_dump(date("Y-m-d H:i:s") . " ::: start convert");
        }
        $data = array();
        foreach ($rs as $r)
        {
            $data[$r[$key]] = $r;
        }
        if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
        {
            var_dump(date("Y-m-d H:i:s") . " ::: end convert");
        }
        return $data;
    }
    
    function fetchOneRow($query, $bind_array = array())
    {
        $this->execute($query, $bind_array);
        return $this->stmt->fetch(PDO :: FETCH_ASSOC);
    }
    
    function fetchOneValue($query, $bind_array = array())
    {
        $this->execute($query, $bind_array);
        $row = $this->stmt->fetch(PDO :: FETCH_ASSOC);
        if (!empty($row))
        {
            return array_shift($row);
        }
        else
        {
            return false;
        }
    }
    
    function nextId()
    {
        return $this->conn->lastInsertId();
    }
    
    function getAffectedRows()
    {
        return $this->stmt->rowCount();
    }
        
    function insert($table, $values)
    {
        $query = "insert into `".$table."` " . $this->preinsert_fields($values) . " values " . $this->preinsert_wildcards($values);
        $this->execute($query, array_values($values));
        return $this->nextId();
    }
    
    function replace($table, $values)
    {
        $query = "replace into `".$table."` " . $this->preinsert_fields($values) . " values " . $this->preinsert_wildcards($values);
        $this->execute($query, array_values($values));
        return $this->nextId();
    }
    
    function update($table, $data, $pk)
    {
        $parts = array();
        $bind = array();
        foreach ($data as $key => $value)
        {
            if ($key != $pk)
            {
                $parts[] = "`" . $key . "` = ?";
                $bind[] = $value;
            }
        }
        $bind[] = $data[$pk];
        $query = "update `" . $table . "` set " . implode(", ", $parts) . " where `" . $pk . "`=?";
        $this->execute($query, $bind);
        return $this->stmt->rowCount();
    }
    
    function execute($query, $bind_array = array(), $args = array())
    {
        if (mtoConf :: instance()->val("db|read_only"))
        {
            if (stripos($query, "insert") === 0 || stripos($query, "update") === 0 || stripos($query, "replace") === 0)
            {
                return true;
            }
        }
        mtoProfiler :: instance()->timerStartCount("dbc");
        mtoProfiler :: instance()->timerStartInc("dbt");
        if (mtoConf :: instance()->get("dfs", "is_master") != 1)
        {
            if (strpos($query, "update") !== false || strpos($query, "insert") !== false)
            {
                mtoProfiler :: instance()->logDebug($query, "query_miss");
                mtoProfiler :: instance()->timerStop("dbt");
                return;
            }
        }
        $this->qcount++;
        $time = microtime(true);
        if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
        {
            var_dump(date("Y-m-d H:i:s") . " ::: start preexecute");
        }
        list($query, $bind_array) = $this->preexecute($query, $bind_array);
        if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
        {
            var_dump(date("Y-m-d H:i:s") . " ::: end preexecute");
        }
               
        try
        {
            if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
            {
                var_dump(date("Y-m-d H:i:s") . " ::: start prepare");
            }
//            if (!empty($args))
//            {
//                foreach ($args as $key => $value)
//                {
//                    switch ($key)
//                    {
//                        case "buffered":
//                            $this->conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
//                        break;
//                    }
//                }
//            }
            $this->stmt = $this->conn->prepare($query);
            if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
            {
                var_dump(date("Y-m-d H:i:s") . " ::: end prepare");
            }
            if (!is_array($bind_array))
            {
                mtoProfiler :: instance()->logDebug($query . "\n" . _D(debug_backtrace(), true, true, true), "query_noarr");
                return $query;
            }
            if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
            {
                var_dump(date("Y-m-d H:i:s") . " ::: start execute");
            }
            $this->stmt->execute($bind_array);
            if (isset($GLOBALS['db_dump_process']) && $GLOBALS['db_dump_process'])
            {
                var_dump(date("Y-m-d H:i:s") . " ::: end execute");
            }
            if (defined("DEBUG_IP_ADDRESS") && isset($_SERVER['REMOTE_ADDR']) && DEBUG_IP_ADDRESS == $_SERVER['REMOTE_ADDR'])
            {
                //mtoProfiler :: instance()->logDebug("[" . round(microtime(true)-$time, 3) . "]\t" . $query . "\t" . json_encode($bind_array), "debug/query/" . str_replace("/", "_", $_SERVER['REQUEST_URI']));
            }
            
            mtoProfiler :: instance()->timerStop("dbt");
            //mtoProfiler::instance()->logConsole($query);
            //mtoProfiler::instance()->logConsole($query);
            
        }
        catch (PDOException $e)
        {
            if (mtoConf :: instance()->get("core", "debug") || isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS || defined("IN_CLI") && IN_CLI)
            {
                echo "ERROR:" . $e->getMessage();
                var_dump($query);
            }
            $msg = array();
            $msg[] = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
            $msg[] = "\n" . $e->getMessage();
            $msg[] = "\n" . $query;
            $msg[] = "\n" . _D(debug_backtrace(), true, true, true);
            if (strpos($e->getMessage(), "Lock wait timeout exceeded") !== false)
            {
                $r = $this->fetch('show processlist');
                $msg[] = "\n" . print_r($r, true);
            }
            __L($msg, "query_error");
            mtoProfiler :: instance()->timerStop("dbt");
            if (defined("IN_CLI") && IN_CLI)
            {
                throw new mtoException("Query error");
            }
            die();
        }
    }
    
    function tableInfo($table_name)
    {
        $tbl_info = array(
            'fields' => array(),
            'primary_key' => "",
            'indexes' => array()
        );
        $rows = $this->fetch("show columns from " . $table_name);
        if (is_array($rows))
        {
            foreach ($rows as $row)
            {
                if (preg_match("#^([0-9a-zA-Z]+)\((\d+)\)$#", $row['Type'], $matches))
                {
                    $size = $matches[2];
                    $type = $matches[1];
                }
                else
                {
                    $size = 0;
                    $type = $row['Type'];
                }
                $tbl_info['fields'][$row['Field']] = array(
                    'name' => $row['Field'],
                    'type' => $type,
                    'size' => $size
                );
                if ($row['Key'] == "PRI")
                {
                    $tbl_info['primary_key'] = $row['Field'];
                }
            }
        }
        return $tbl_info;
    }
    
    function escape($value)
    {
        return $this->conn->quote($value);
    }

    
    private function preinsert_fields($values)
    {
        $list = array();
        foreach ($values as $key => $value)
        {
            $list[] = "`" . $key . "`";
        }
        return "(" . implode(", ", $list) . ")";
    }
    
    private function preinsert_wildcards($values)
    {
        $list = array();
        foreach ($values as $value)
        {
            $list[] = "?";
        }
        return "(" . implode(", ", $list) . ")";
    }
    
    
    
    private function preexecute($query, $bind_array = array())
    {
        $new_query = $query;
        $new_bind = $bind_array;
        if (strpos($query, "(?)") !== false)
        {
            $new_bind = array();
            foreach ($bind_array as $bind)
            {
                if (is_array($bind))
                {
                    $wildcard = array();
                    if (empty($bind))
                    {
                        $bind[] = 0;
                    }
                    foreach ($bind as $v)
                    {
                        $new_bind[] = $v;
                        $wildcard[] = "?";
                    }
                    $new_query = preg_replace("#\(\?\)#", "(".implode(",", $wildcard).")", $new_query, 1);
                }
                else
                {
                    $new_bind[] = $bind;
                }
            }
        }
        return array($new_query, $new_bind);
    }
    
    function getQueryCount()
    {
        return $this->qcount;
    }
    
    
    
    //legacy
    function sql_getall($query, $bind_array = array())
    {
        return $this->fetch($query, $bind_array);
    }
    
    function sql_keygetall($key, $query, $bind_array = array())
    {
        return $this->fetchByKey($key, $query, $bind_array);
    }
    
    function sql_getone($query, $bind_array = array())
    {
        return $this->fetchOneRow($query, $bind_array);
    }
    
    function sql_query($query, $bind_array = array())
    {
        return $this->execute($query, $bind_array);
    }
    
    function sql_fetchrow($res = 0)
    {
        return $this->stmt->fetch(PDO :: FETCH_ASSOC);
    }
    
    function sql_fetchrowset($res = 0)
    {
        return $this->stmt->fetchAll(PDO :: FETCH_ASSOC);
    }
    
    function sql_affectedrows()
    {
        return $this->getAffectedRows();
    }
    
    function sql_numrows($res = 0)
    {
        return $this->stmt->rowCount();
    }
    
    function sql_nextid()
    {
        return $this->nextId();
    }
    
    function sql_insert($table, $values, $replace = false)
    {
        if ($replace)
        {
            return $this->replace($table, $values);
        }
        else
        {
            return $this->insert($table, $values);
        }

    }

    function sql_replace($table, $values)
    {
        return $this->replace($table, $values);
    }

    public function sql_update($table, $data, $pk)
    {
        return $this->update($table, $data, $pk);
    }
    
    public function sql_getvalue($table, $key_field, $value_field, $key_value, $where = "")
    {
        return $this->fetchOneValue("select " . $value_field . " from " . $table . " where " . $key_field . " = '" . $key_value . "' " . $where);
    }
    
    public function sql_inspect_table($table_name)
    {
        return $this->tableInfo($table_name);
    }
    
    
    public function sql_tableinfo($table)
    {
        $info = $this->sql_inspect_table($table);
        return $info['fields'];
    }
    
    
}