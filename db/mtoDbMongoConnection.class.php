<?php
mtoClass :: import("mtokit/db/mtoDb.class.php");

class mtoDbMongoConnection
{
    
    protected $conn;
    protected $db;
    protected $stmt;
    protected $qcount = 0;
    
    function __construct($dsn_string)
    {
        mtoProfiler :: instance()->timerStartCount("mng_connect_c");
        mtoProfiler :: instance()->timerStartInc("mng_connect_t");
        list($dsn, $login, $password, $params) = explode("|", $dsn_string);
        try
        {
            $args = array();
            if (!empty($params))
            {
                $parts = explode(";", $params);
                foreach ($parts as $part)
                {
                    if (strpos($part, "=") !== false)
                    {
                        list($k, $v) = explode("=", $part);
                        $args[$k] = $v;
                    }
                }
            }
            $this->conn = new MongoClient($dsn, array('connect' => true, 'connectTimeoutMS' => 3000));
            if (empty($args['dbname']))
            {
                mtoProfiler :: instance()->timerStop("mng_connect_t");
                throw new mtoException("Mongo database is not set");
            }
            $this->db = $this->conn->selectDB($args['dbname']);
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\tconnected", "debug/mongo");
        }
        catch (MongoException $e)
        {
            mtoProfiler :: instance()->logDebug($e->getMessage(), "query_mongo");
        }
        mtoProfiler :: instance()->timerStop("mng_connect_t");
    }
    
    function fetch($query, $bind_array = array(), $args = array())
    {
        mtoProfiler :: instance()->timerStartCount("mng_get_c");
        mtoProfiler :: instance()->timerStartInc("mng_get_t");
        $coll = $this->db->selectCollection($query);
        $r = $coll->find($bind_array);
        mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\tGET\t" . $query . "\t" . json_encode($bind_array), "debug/mongo");
        mtoProfiler :: instance()->timerStop("mng_get_t");
        return $r;
    }
    
    function fetchByKey($key, $query, $bind_array = array(), $args = array())
    {
        mtoProfiler :: instance()->timerStartCount("mng_get_c");
        mtoProfiler :: instance()->timerStartInc("mng_get_t");
        $coll = $this->db->selectCollection($query);
        $r = $coll->findOne(array('_id' => $key));
        mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\tGET\t" . $query . "\t" . $key, "debug/mongo");
        mtoProfiler :: instance()->timerStop("mng_get_t");
        return $r;
    }
    
    function fetchOneRow($query, $bind_array = array())
    {
        throw new mtoException("Not implemented");
//        $this->execute($query, $bind_array);
//        return $this->stmt->fetch(PDO :: FETCH_ASSOC);
    }
    
    function fetchOneValue($query, $bind_array = array())
    {
        throw new mtoException("not implemented");
//        mtoProfiler :: instance()->timerStartCount("mng_get_c");
//        mtoProfiler :: instance()->timerStartInc("mng_get_t");
//        if (empty($query['collection']))
//        {
//            throw new mtoException("Collection name is not set");
//        }
//        $coll = $this->db->selectCollection($query['collection']);
//        $c = $query['collection'];
//        unset($query['collection']);
//        $r = $coll->findOne($query);
//        mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\tGET\t" . $c . "\t" . $query['_id'], "debug/mongo");
//        mtoProfiler :: instance()->timerStop("mng_get_t");
//        return $r;
    }
    
    function nextId()
    {
        throw new mtoException("not implemented");
        //return $this->conn->lastInsertId();
    }
        
    function insert($table, $values)
    {
        $coll = $this->db->selectCollection($table);
        return $coll->insert($values);
    }
    
    function replace($table, $values)
    {
        mtoProfiler :: instance()->timerStartCount("mng_set_c");
        mtoProfiler :: instance()->timerStartInc("mng_set_t");
        $coll = $this->db->selectCollection($table);
        $r = $coll->save($values);
        mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\tSET\t" . $table . "\t" . $values['_id'], "debug/mongo");
        mtoProfiler :: instance()->timerStop("mng_set_t");
        return $r;
    }
    
    function update($table, $data, $pk)
    {
        throw new mtoException("not implemented");
//        $parts = array();
//        $bind = array();
//        foreach ($data as $key => $value)
//        {
//            if ($key != $pk)
//            {
//                $parts[] = "`" . $key . "` = ?";
//                $bind[] = $value;
//            }
//        }
//        $bind[] = $data[$pk];
//        $query = "update `" . $table . "` set " . implode(", ", $parts) . " where `" . $pk . "`=?";
//        $this->execute($query, $bind);
//        return $this->stmt->rowCount();
    }
    
    function execute($query, $bind_array = array(), $args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function tableInfo($table_name)
    {
        throw new mtoException("not implemented");
    }
    
    function escape($value)
    {
        throw new mtoException("not implemented");
    }

    
    
    function getQueryCount()
    {
        return $this->qcount;
    }

    function drop($collection)
    {
        $coll = $this->db->selectCollection($collection);
        return $coll->drop();
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
    
//    function sql_fetchrow($res = 0)
//    {
//        return $this->stmt->fetch(PDO :: FETCH_ASSOC);
//    }
    
//    function sql_fetchrowset($res = 0)
//    {
//        return $this->stmt->fetchAll(PDO :: FETCH_ASSOC);
//    }
    
//    function sql_affectedrows()
//    {
//        return $this->stmt->rowCount();
//    }
    
//    function sql_numrows($res = 0)
//    {
//        return $this->stmt->rowCount();
//    }
    
//    function sql_nextid()
//    {
//        return $this->nextId();
//    }
    
//    function sql_insert($table, $values, $replace = false)
//    {
//        if ($replace)
//        {
//            return $this->replace($table, $values);
//        }
//        else
//        {
//            return $this->insert($table, $values);
//        }
//
//    }

//    function sql_replace($table, $values)
//    {
//        return $this->replace($table, $values);
//    }

//    public function sql_update($table, $data, $pk)
//    {
//        return $this->update($table, $data, $pk);
//    }
    
//    public function sql_getvalue($table, $key_field, $value_field, $key_value, $where = "")
//    {
//        return $this->fetchOneValue("select " . $value_field . " from " . $table . " where " . $key_field . " = '" . $key_value . "' " . $where);
//    }
    
//    public function sql_inspect_table($table_name)
//    {
//        return $this->tableInfo($table_name);
//    }
    
    
//    public function sql_tableinfo($table)
//    {
//        $info = $this->sql_inspect_table($table);
//        return $info['fields'];
//    }
    
    
}