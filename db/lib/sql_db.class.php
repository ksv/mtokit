<?php

class sql_db
{

    private $db_connect_id;
    private $query_result;
    private $row = array();
    private $rowset = array();
    public $num_queries = 0;

    public function __construct($sqlserver, $sqluser, $sqlpassword, $database, $persistency = false)
    {

        $this->persistency = $persistency;
        $this->user = $sqluser;
        $this->password = $sqlpassword;
        $this->server = $sqlserver;
        $this->dbname = $database;

        $this->query_result = "";
    }

    protected function establish_connection()
    {
        if (!$this->db_connect_id)
        {
            if ($this->persistency)
            {
                $this->db_connect_id = mysql_pconnect($this->server, $this->user, $this->password);
            }
            else
            {
                $this->db_connect_id = mysql_connect($this->server, $this->user, $this->password, 1);
            }

            if ($this->dbname != "")
            {
                $dbselect = mysql_select_db($this->dbname, $this->db_connect_id);
                if (!$dbselect)
                {
                    mysql_close($this->db_connect_id);
                    $this->db_connect_id = $dbselect;
                }
            }
        }
    }

    public function sql_close()
    {
        if ($this->db_connect_id)
        {
            if ($this->query_result)
            {
                @mysql_free_result($this->query_result);
            }
            $result = @mysql_close($this->db_connect_id);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_getall($query = "", $bind_array = array(), $bind_rules = array(), $transaction = FALSE, $key = "")
    {
        $res = $this->sql_query($query, $bind_array, $bind_rules, $transaction);
        if ($res && is_resource($res))
        {
            return $this->sql_fetchrowset($res, $key);
        }
        elseif ($res)
        {
            return $res;
        }
        else
        {
            return false;
        }
    }

    static function fetch($query)
    {
        $db = getDBConnection();
        return $db->sql_getall($query);
    }

    public function sql_keygetall($key, $query = "", $bind_array = array(), $bind_rules = array(), $transaction = FALSE)
    {
        return $this->sql_getall($query, $bind_array, $bind_rules, $transaction, $key);
    }

    public function sql_getone($query = "", $bind_array = array(), $bind_rules = array(), $transaction = FALSE)
    {
        $res = $this->sql_query($query, $bind_array, $bind_rules, $transaction);
        if ($res && is_resource($res))
        {
            return $this->sql_fetchrow($res);
        }
        elseif ($res)
        {
            return $res;
        }
        else
        {
            return false;
        }
    }

    static function fetchOneRow($query)
    {
        $db = getDBConnection();
        return $db->sql_getone($query);
    }
    
    static function fetchOneValue($sql)
    {
        $db = getDBConnection();
        $row = $db->sql_getone($sql);
        if (is_array($row))
        {
            return array_pop($row);
        }
        else
        {
            return null;
        }
    }

    public function sql_query($query = "", $bind_array = array(), $bind_rules = array(), $transaction = FALSE)
    {
        if (mtoConf :: instance()->get("dfs", "is_master") != 1)
        {
            if (strpos($query, "update") !== false || strpos($query, "insert") !== false)
            {
                mtoProfiler :: instance()->logDebug($query, "query_miss");
                return;
            }
        }
        $time = microtime(true);
        $this->establish_connection();

        if (is_array($bind_array) && count($bind_array) > 0)
        {
            $query = $this->sql_prepare($query, $bind_array, $bind_rules);
        }
        $this->query_result = 0;
        if ($query != "")
        {
            //var_dump($query);
            $this->num_queries++;
            if (isset($GLOBALS['dump_query']) && $GLOBALS['dump_query'])
            {
                var_dump($query);
            }
            $this->query_result = mysql_query($query, $this->db_connect_id);
            if (isset($_REQUEST['qprof']) && $_REQUEST['qprof'])
            {
                mtoProfiler :: instance()->logDebug(round(microtime(true) - $time, 2) . "\t" . $query, "query_timing");
            }
            //var_dump($query);
            if (mysql_errno($this->db_connect_id) > 0)
            {
                if ((isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS) || (defined("IN_CLI") && IN_CLI))
                {
                    echo "ERROR:" . mysql_error($this->db_connect_id);
                    var_dump($query);
                    //_D(debug_backtrace(), true);
                }
                mtoProfiler :: instance()->logDebug(mysql_error() . "\n" . $query . "\n" . _D(debug_backtrace(), true, true, true), "query_error");
                die();
                //print_r(debug_backtrace());
                //throw new pdException("database_error", 0, array($query, mysql_error()));
            }
        }
        if ($this->query_result)
        {
            if (isset($this->row[$this->query_result]))
                unset($this->row[$this->query_result]);
            if (isset($this->rowset[$this->query_result]))
                unset($this->rowset[$this->query_result]);
            return $this->query_result;
        }
//        else
//        {
//            return ( $transaction == END_TRANSACTION ) ? true : false;
//        }
    }

    static function execute($sql)
    {
        $db = getDBConnection();
        return $db->sql_query($sql);
    }

    public function sql_numrows($query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            $result = @mysql_num_rows($query_id);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_affectedrows()
    {
        if ($this->db_connect_id)
        {
            $result = @mysql_affected_rows($this->db_connect_id);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_numfields($query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            $result = @mysql_num_fields($query_id);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_fieldname($offset, $query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            $result = @mysql_field_name($query_id, $offset);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_fieldtype($offset, $query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            $result = mysql_field_type($query_id, $offset);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_fetchrow($query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            $this->row[(int) $query_id] = mysql_fetch_assoc($query_id);
            return $this->row[(int) $query_id];
        }
        else
        {
            return false;
        }
    }

    public function sql_fetchrowset($query_id = 0, $key = null)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            unset($this->rowset[(int) $query_id]);
            unset($this->row[(int) $query_id]);
            $result = array();
            while ($this->rowset[(int) $query_id] = mysql_fetch_assoc($query_id))
            {
                if (!empty($key) && isset($this->rowset[(int) $query_id][$key]))
                {
                    $result[$this->rowset[(int) $query_id][$key]] = $this->rowset[(int) $query_id];
                }
                else
                {
                    $result[] = $this->rowset[(int) $query_id];
                }
            }
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_fetchfield($field, $rownum = -1, $query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            if ($rownum > -1)
            {
                $result = @mysql_result($query_id, $rownum, $field);
            }
            else
            {
                if (empty($this->row[$query_id]) && empty($this->rowset[$query_id]))
                {
                    if ($this->sql_fetchrow())
                    {
                        $result = $this->row[$query_id][$field];
                    }
                }
                else
                {
                    if ($this->rowset[$query_id])
                    {
                        $result = $this->rowset[$query_id][$field];
                    }
                    else if ($this->row[$query_id])
                    {
                        $result = $this->row[$query_id][$field];
                    }
                }
            }
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_tableinfo($table)
    {
        $query_id = $this->sql_query("select * from $table limit 1");
        $table_info = array();
        for ($i = 0; $i < $this->sql_numfields($query_id); $i++)
        {
            $name = $this->sql_fieldname($i, $query_id);
            $type = $this->sql_fieldtype($i, $query_id);
            $table_info[$name] = array(
                'name' => $name,
                'type' => $type
            );
        }
        return $table_info;
    }

    public function sql_rowseek($rownum, $query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }
        if ($query_id)
        {
            $result = @mysql_data_seek($query_id, $rownum);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_nextid()
    {
        if ($this->db_connect_id)
        {
            $result = @mysql_insert_id($this->db_connect_id);
            return $result;
        }
        else
        {
            return false;
        }
    }

    public function sql_freeresult($query_id = 0)
    {
        if (!$query_id)
        {
            $query_id = $this->query_result;
        }

        if ($query_id)
        {
            unset($this->row[$query_id]);
            unset($this->rowset[$query_id]);

            @mysql_free_result($query_id);

            return true;
        }
        else
        {
            return false;
        }
    }

    public function sql_error($query_id = 0)
    {
        $result["message"] = @mysql_error($this->db_connect_id);
        $result["code"] = @mysql_errno($this->db_connect_id);

        return $result;
    }

    public function sql_updateconfig($table, $key_field, $value_field, $values)
    {
        $this->sql_query("update $table set $value_field = '0' where cfg_type=?", array(Config :: TYPE_BOOL));
        while (list($key, $value) = each($values))
        {
            $this->sql_query("update $table set $value_field = '$value' where $key_field = '" . strtoupper($key) . "'");
        }
    }

    function sql_getinsertsql($table, $arrFields)
    {
        if (is_array($arrFields) && count($arrFields))
        {
            $fields = array_keys($arrFields);
            $values = array_values($arrFields);
            foreach ($values as $key => $value)
            {
                $values[$key] = $this->_prepare_value($value, "string");
            }
            $sql = "INSERT INTO $table (" . implode(",", $fields) . ") VALUES (" . implode(",", $values) . ")";
            return $sql;
        }
        else
        {
            return false;
        }
    }

    function sql_insert($table, $values, $replace = false)
    {
        $fields = array();
        $wildcards = array();
        $binds = array();
        foreach ($values as $key => $value)
        {
            $fields[] = "`" . $key . "`";
            $wildcards[] = "?";
            $binds[] = $value;
        }
        if ($replace)
        {
            $s = "replace";
        }
        else
        {
            $s = "insert";
        }
        $query = $s . " into `" . $table . "` (" . implode(",", $fields) . ") values (" . implode(",", $wildcards) . ")";
        $this->sql_query($query, $binds);
        return $this->sql_nextid();
    }

    function sql_replace($table, $values)
    {
        return $this->sql_insert($table, $values, true);
    }

    public function sql_makeinsert($table, $data)
    {
        $fields = "";
        $values = "";
        while (list($key, $value) = each($data))
        {
            if (is_numeric($key))
                continue;
            if (!preg_match("#^submit\_#", $key) && $key != "mode" && $key != "action")
            {
                $fields.=$key . ", ";
                $values.="'" . $value . "', ";
            }
        }
        $values = substr($values, 0, -2);
        $fields = substr($fields, 0, -2);
        $sql = "insert into $table ($fields) values ($values)";
        $this->sql_query($sql);
    }

    public function sql_makeupdate($table, $data, $key, $key_update = 0)
    {
        $upd = "";
        $binds = array();
        while (list($k, $v) = each($data))
        {
            if (is_numeric($k))
                continue;
            if ($k == "old_" . $key)
                continue;
            if (!preg_match("#^submit\_#", $k) && $k != "mode" && $k != "action")
            {
                if ($k != $key)
                {
                    $upd.=$k . " = ?, ";
                    $binds[] = $v;
                }
                else
                {
                    if ($key_update == 1)
                    {
                        $upd.=$k . " = ?, ";
                        $binds[] = $v;
                    }
                }
            }
        }
        if ($key_update == 1)
        {
            $key_field = "old_" . $key;
        }
        else
        {
            $key_field = $key;
        }
        $upd = substr($upd, 0, -2);
        $sql = "update $table set $upd where $key = ?";
        $binds[] = $data[$key_field];
        $this->sql_query($sql, $binds);
    }

    public function sql_update($table, $data, $pk)
    {
        $pairs = array();
        $binds = array();
        foreach ($data as $key => $value)
        {
            if (is_numeric($key))
                continue;
            if ($key == $pk)
                continue;
            $pairs[] = "`" . $key . "`=?";
            $binds[] = $value;
        }
        $query = "update `" . $table . "` set " . implode(",", $pairs) . " where `" . $pk . "`=?";
        $binds[] = $data[$pk];
        $this->sql_query($query, $binds);
        return $this->sql_affectedrows();
    }

    public function sql_makeoptions($table, $value_field, $text_field, $selected = "", $show_default = 1, $pattern = "", $override_default = array(), $extra_fields = array(), $use_cache = false)
    {
        $sql = "select * from " . $table . (($pattern) ? " " . $pattern : "");
        $key = "sql_makeoptions_" . md5($sql);
        if ($show_default == 1)
        {
            if (isset($override_default['text']) && isset($override_default['value']))
            {
                $str = "<option value='" . $override_default['value'] . "'>" . $override_default['text'] . "\n";
            }
            else
            {
                $str = "<option value='0'>-------\n";
            }
        }
        else
        {
            $str = "";
        }
        $options = array();
        if (!is_array($options) || !count($options))
        {
            $options = $this->sql_fetchrowset($this->sql_query($sql));
            if ($use_cache)
            {
                // $cache->set($key, $options);
            }
        }
        for ($i = 0; $i < count($options); $i++)
        {
            if (count($extra_fields))
            {
                $add_str = " ";
                foreach ($extra_fields as $field)
                {
                    $add_str .= $field .= "=\"" . (isset($options[$i][$field]) ? $options[$i][$field] : "") . "\"";
                }
                $add_str .= " ";
            }
            else
            {
                $add_str = "";
            }
            if ($selected && ($options[$i][$value_field] == $selected || (is_array($selected) && in_array($options[$i][$value_field], $selected))))
            {
                $sel = "selected";
            }
            else
            {
                $sel = "";
            }
            $str.="<option value='" . $options[$i][$value_field] . "' " . $sel . $add_str . ">" . $options[$i][$text_field] . "\n";
        }
        return $str;
    }

    public function sql_getvalue($table, $key_field, $value_field, $key_value, $where = "")
    {
        $res = $this->sql_fetchrow($this->sql_query("select " . $value_field . " from " . $table . " where " . $key_field . " = '" . $key_value . "' " . $where));
        return $res[$value_field];
    }

    private function _prepare_value($value, $rule)
    {
        if (empty($value))
            return "''";
        switch ($rule)
        {
            case "money":
                return str_replace("\$", "", str_replace(",", "", $value));
                break;
            case "noent":
                $value = stripslashes($value);
                return "'" . mysql_real_escape_string($value) . "'";
                break;
            default:
                switch (gettype($value))
                {
                    case "string":
                        $value = stripslashes($value);
                        //$value = htmlspecialchars(html_entity_decode($value, ENT_QUOTES), ENT_QUOTES);
                        return "'" . mysql_real_escape_string($value) . "'";
                        break;
                    case "double":
                        return str_replace(",", ".", $value);
                        break;
                    case "array":
                        return implode(",", $value);
                        break;
                    default:
                        if (empty($value) && $value !== 0)
                            return "";
                        return "'" . $value . "'";
                        break;
                }
                break;
        }
    }

    public function sql_prepare($sql, $bind = array(), $rules = array())
    {
        if (is_array($bind) && count($bind) > 0)
        {
            if (count($bind) != substr_count($sql, "?"))
            {
                die("Sql prepare error. Number of wildcards is not same as number of binds");
                return false;
            }
            $sql_parts = explode("?", $sql);
            $new_sql = "";
            for ($i = 0; $i < count($sql_parts); $i++)
            {
                $new_sql .= $sql_parts[$i] .= (isset($bind[$i]) ? ($this->_prepare_value($bind[$i], isset($rules[$i]) ? $rules[$i] : "")) : ($i < count($sql_parts) - 1 ? "''" : ""));
            }
        }
        else
        {
            $new_sql = $sql;
        }
        return $new_sql;
    }

    public function sql_inspect_table($table_name)
    {
        $tbl_info = array(
            'fields' => array(),
            'primary_key' => "",
            'indexes' => array()
        );
        $rows = $this->sql_getall("show columns from " . $table_name);
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
        return mysql_real_escape_string($value);
    }

}

// class sql_db




