<?php
class TemplateTagDbtable extends TemplateTagAbstract
{
    
    protected $required = array("table", "allow_insert", "allow_delete");
    protected $optional = array(
        'pk' => 'id'    
    );
    protected $db;
    protected $table_info = array();
    protected $extra_params = array();
    protected $offset;
    
    public function compile($code)
    {
        mtoClass :: import("classes/model/Dummy.class.php");
        if (isset($this->params['allow_edit']) && $this->params['allow_edit'] == "false")
        {
            $this->params['allow_insert'] = false;
            $this->params['allow_delete'] = false;
        }
        $handle = uniqid('tpldbtable');
        $this -> validate_required();
        $this -> append_optional();
        $this->db = mtoToolkit :: instance()->getDbConnection();
        $this->table_info = $this->db->sql_inspect_table($this->params['table']);
        if (!empty($this->params['helper']))
        {
            $this->extra_params = parse_ini_file("shared/ini/".$this->params['helper'].".ini", true);
        }
        $this->append_table_info();
        if (isset($this->extra_params['fields_order']))
        {
            $this->reorder_columns();
        }
        $this -> tplobj -> set_filenames(array($handle => 'core/table_editor.tpl'));
        
        $submit = mtoToolkit :: instance()->getRequest()->get("submit_dbtable");
        if (!empty($submit))
        {
            $this->process_table();
        }
        $delete = mtoToolkit :: instance()->getRequest()->getInteger("delete_row");
        if (!empty($delete))
        {
            $this->process_delete($delete);
        }
        
        $this->process_headers();
        if ($this->params['allow_insert'] == 1 || $this->params['allow_insert'] == "true")
        {
            $this->process_insert_line("top");
        }
        $this->process_data();
        if ($this->params['allow_insert'] == 1 || $this->params['allow_insert'] == "true")
        {
            $this->process_insert_line();
        }
        $this->tplobj->assign_vars(array(
            'TABLE' => $this->params['table'],
            'CELL_CLASS' => "admin_text",
            'TBL_NAME' => isset($this->params['name']) ? $this->params['name'] : ""
        ));
        
        $this->replacement = $this->tplobj->pparse($handle, true);
        $this -> replacement = str_replace('\\', '\\\\', $this -> replacement);
        $this -> replacement = str_replace('\'', '\\\'', $this -> replacement);
        return $this->replace($code);
    }
    
    private function process_headers()
    {
        if (isset($this->params['selector']))
        {
            $this->tplobj->assign_block_vars("selector", array());
        }
        foreach ($this->table_info['fields'] as $key => $field)
        {
            if (isset($this->extra_params['config']) && isset($this->extra_params['config']['hide_unknown']) && !in_array($key, array_keys($this->extra_params['captions'])))
            {
                continue;
            }
            if (isset($this->extra_params['hide']) && in_array($key, array_keys($this->extra_params['hide']))) continue;
            if ($key != $this->table_info['primary_key'] || isset($this->params['show_pk']))
            {
                if (isset($this->extra_params['captions'][$key]))
                {
                    $this->tplobj->assign_block_vars('head', array(
                        'HEAD' => $this->extra_params['captions'][$key]
                    ));
                }
                else
                {
                    $this->tplobj->assign_block_vars('head', array(
                        'HEAD' => $key
                    ));
                }
            }
            $this->tplobj->assign_vars(array(
                'HEAD_CLASS' => "admin_header",
                'TABLE_CLASS' => "admin_bg"
            ));
        }
    }
    
    private function process_insert_line($prefix="")
    {
        $this->tplobj->assign_block_vars($prefix."insert", array());
        if (isset($this->params['selector']))
        {
            $this->tplobj->assign_block_vars($prefix."insert.selector", array());
        }
        foreach ($this->table_info['fields'] as $key => $field)
        {
            if ($key == $this->table_info['primary_key'] && !isset($this->params['show_pk'])) continue;
            if (isset($this->extra_params['config']) && isset($this->extra_params['config']['hide_unknown']) && !in_array($key, array_keys($this->extra_params['captions'])))
            {
                continue;
            }
            if (isset($this->extra_params['hide']) && in_array($key, array_keys($this->extra_params['hide']))) continue;
            $this->tplobj -> assign_block_vars($prefix.'insert.icell', array(
                'VALUE' => ($field['type'] == "int" && $field['size'] == 1) || in_array($key, array_keys($this->extra_params['checkboxes'])) ? 1 : "",
                'ALIGN' => isset($this->extra_params['aligns'][$key]) ? $this->extra_params['aligns'][$key] : "center",
                'FIELD' => $key,
            ));
            if (isset($this->extra_params['readonly']) && in_array($key, array_keys($this->extra_params['readonly'])))
            {
                $this->tplobj->assign_block_vars($prefix."insert.icell.empty", array());
            }
            elseif (($field['type'] == "int" && $field['size'] == 1) || in_array($key, array_keys($this->extra_params['checkboxes'])))
            {
                $this->tplobj->assign_block_vars($prefix."insert.icell.checkbox", array());
            }
            elseif($field['type'] == "text" || $field['type'] == "mediumtext")
            {
                $this->tplobj->assign_block_vars($prefix."insert.icell.textarea", array(
                    'PARAMS' => isset($this->extra_params['textareas']) && isset($this->extra_params['textareas'][$key]) ? $this->extra_params['textareas'][$key] : ""
                ));
            }
            elseif (in_array($key, array_keys($this->extra_params['references'])))
            {
                $method = $this->extra_params['references'][$key];
                $this->tplobj->assign_block_vars($prefix."insert.icell.select", array(
                    'OPTIONS' => HTML::options_by_array(0, $this->$method(), 1)
                ));
            }
            elseif (isset($this->extra_params['box_references']) && in_array($key, array_keys($this->extra_params['box_references'])))
            {
                $method = $this->extra_params['box_references'][$key]."_insert_line";
                $this->tplobj->assign_block_vars($prefix."insert.icell.boxlist", array(
                ));
                if (method_exists($this, $method))
                {
                    $this->$method($prefix);
                }
            }
            elseif (isset($this->extra_params['files']) && isset($this->extra_params['files'][$key]) && isset($this->extra_params['file_'.$key]))
            {
                $this->tplobj->assign_block_vars($prefix."insert.icell.file", array());
            }
            elseif (isset($this->extra_params['dates']) && in_array($key, array_keys($this->extra_params['dates'])))
            {
                $this->tplobj->assign_block_vars($prefix."insert.icell.datebox", array());
            }
            else
            {
                $this->tplobj->assign_block_vars($prefix."insert.icell.textbox", array());
            }
        }
    }
    
    
    
    private function calc_color($index, $row)
    {
        static $override = null;
        
        $color = $index % 2 == 0 ? "#FFFFFF" : "#CCCCCC";
        if (is_null($override))
        {
            if (isset($this->extra_params['override_colors']) && is_array($this->extra_params['override_colors']))
            {
                $override = array();
                foreach ($this->extra_params['override_colors'] as $key => $value)
                {
                    if (preg_match("#^method_#", $value))
                    {
                        if (!isset($override['call']))
                        {
                            $override['call'] = array();
                        }
                        $override['call'][] = str_replace("method_", "", $value);
                    }
                    else
                    {
                        $arr = parse_ini_file("shared/ini/".$value.".ini");
                        foreach ($arr as $k=>$v)
                        {
                            if ($k == strtoupper($k))
                            {
                                $arr[$k] = $v;
                            }
                        }
                        $override[$key] = $arr;
                    }
                }
            }
            else
            {
                $override = array();
            }
        }
        if (!empty($override))
        {
            foreach ($override as $key=>$value)
            {
                if ($key == 'call') continue;
                if (isset($value[$row[$key]]))
                {
                    $color = $value[$row[$key]];
                }
            }
            if (isset($override['call']) && is_array($override['call']))
            {
                foreach ($override['call'] as $method)
                {
                    $color = $this->$method($row, $color);
                }
            }
        }
        return $color;
    }
    
    
    private function get_sql()
    {
        if (isset($this->params['sql']))
        {
            $sql = html_entity_decode($this->params['sql'], ENT_QUOTES);
            $sql = stripslashes($sql);
        }
        else
        {
            if ($this->get_limit())
            {
                $sql = "select sql_calc_found_rows * from ".$this->params['table'];
            }
            else
            {
                $sql = "select * from ".$this->params['table'];
            }
        }
        if (isset($this->params['where']))
        {
            $where = trim($this->params['where']);
            if (!empty($where))
            {
                $sql .= " where " . html_entity_decode($where, ENT_QUOTES);
            }
        }
        if (isset($this->params['order']))
        {
            $sql .= " order by ".$this->params['order'];
        }
        $sql .= $this->get_limit();
        return $sql;
    }
    
    private function get_limit()
    {
        if (!empty($this->params['limit']))
        {
            $this->offset = mtoToolkit :: instance()->getRequest()->getInteger("start", 1);
            return " limit " . intval($this->offset-1) . ", " . intval($this->params['limit']);
        }
        else
        {
            return "";
        }
    }
    
    private function process_data()
    {
        $data = $this->db->sql_getall($this->get_sql());
        //var_dump($this->get_sql());
        if ($this->get_limit())
        {
            $row = $this->db->sql_getone("select found_rows() as dbtbl_count");
            $this->tplobj->assign_block_vars("pager_section", array(
                'PAGER' => Block :: block_pager(array(
                            "items_per_page" => $this->params['limit'],
                            "urltpl" => $this->params['urltpl'],
                            "start" => $this->offset,
                            "total" => $row['dbtbl_count'],
                            "pagination_method" => isset($this->params['pagination_method']) ? $this->params['pagination_method'] : null
                ))
            ));
        }
        $index = 0;
        $group_values = array();
        $current_group = array();
        $groups = array();
        foreach ($data as $row)
        {
            $this->tplobj->assign_block_vars("row", array(
                'PK_VALUE' => $row[$this->table_info['primary_key']],
                'BGCOLOR' => $this->calc_color($index, $row)
            ));
            if (isset($this->extra_params['delimiter']) && is_array($this->extra_params['delimiter']) && !isset($GLOBALS['dbtable_skip_delimiter']))
            {
                if (isset($this->extra_params['delimiter_aggregate']) && is_array($this->extra_params['delimiter_aggregate']))
                {
                    foreach ($this->extra_params['delimiter_aggregate'] as $key => $value)
                    {
                        list($caption, $func) = explode("|", $value);
                        if (!isset($current_group[$key]))
                        {
                            $current_group[$key] = 0;
                        }
                        switch ($func)
                        {
                            case "sum":
                                $current_group[$key] += $row[$key];
                            break;
                            case "count":
                                if (isset($row[$key]))
                                {
                                    $current_group[$key]++;
                                }
                            break;
                        }
                    }
                }
                foreach ($this->extra_params['delimiter'] as $key => $value)
                {
                    $v = $this->preexec($value, $row[$key], $row, $key);
                    if (!isset($group_values[$key]) || $group_values[$key] != $v)
                    {
                        $this->tplobj->assign_block_vars("row.delimiter", array(
                            'VALUE' => $v,
                            'COLSPAN' => count($this->extra_params['captions'])+1+(isset($this->params['selector']) ? 1 : 0)
                        ));
                        if (isset($this->extra_params['delimiter_aggregate']) && is_array($this->extra_params['delimiter_aggregate']))
                        {
                            if (!empty($current_group))
                            {
                                $aggregate = array();
                                foreach ($this->extra_params['delimiter_aggregate'] as $akey => $avalue)
                                {
                                    list($acaption, $afunc) = explode("|", $avalue);
                                    $aggregate[] = $acaption .": " . (isset($current_group[$akey]) ? $current_group[$akey] : "");
                                }
                                //$last_delim_arr[0]['AGGREGATE'] = "(" . implode(",", $aggregate) . ")";
                                //$this->tplobj->append_block_stub("row", "delimiter", array('AGGREGATE' => implode(", ", $aggregate)));
                                $groups[] = array('AGGREGATE' => "(" . implode(", ", $aggregate) . ")");
                                $current_group = array();
                            }
                        }
                        $group_values[$key] = $v;
                    }
                }
            }
            if (!isset($this->params['allow_edit']) || $this->params['allow_edit'] != "false")
            {
                $this->tplobj->assign_block_vars("row.form", array());
            }
            if (isset($this->params['selector']))
            {
                $this->tplobj->assign_block_vars("row.selector", array());
            }
            foreach ($this->table_info['fields'] as $key => $field)
            {
                if ($key == $this->table_info['primary_key'] && !isset($this->params['show_pk'])) continue;
                if (isset($this->extra_params['config']) && isset($this->extra_params['config']['hide_unknown']) && !in_array($key, array_keys($this->extra_params['captions'])))
                {
                    continue;
                }
                if (isset($this->extra_params['hide']) && in_array($key, array_keys($this->extra_params['hide']))) continue;
                $row[$key."_original"] = $row[$key];
                if (isset($this->extra_params['preexec']) && isset($this->extra_params['preexec'][$key]))
                {
                    $row[$key] = $this->preexec($this->extra_params['preexec'][$key], $row[$key], $row, $key);
                }
                $append_html = "";
                if (isset($this->extra_params['cell_append_html'][$key]))
                {
                    $method = "append_html_" . $this->extra_params['cell_append_html'][$key];
                    if (!empty($method))
                    {
                        if (method_exists($this, $method))
                        {
                            $append_html = $this->$method($row);
                        }
                    }
                }
                $this->tplobj->assign_block_vars("row.cell", array(
                    'VALUE' => ($field['type'] == "int" && $field['size'] == 1) || in_array($key, array_keys($this->extra_params['checkboxes'])) ? ($row[$key] ? 1 : 0) : $row[$key],
                    'ALIGN' => isset($this->extra_params['aligns'][$key]) ? $this->extra_params['aligns'][$key] : "center",
                    'NOWRAP' => isset($this->extra_params['nowrap'][$key]) ? 'nowrap="nowrap"' : "",
                    'FIELD' => $key,
                    'APPEND_HTML' => $append_html,
                    'CHECKED' => (($field['type'] == "int" && $field['size'] == 1) || in_array($key, array_keys($this->extra_params['checkboxes']))) && $row[$key] ? "checked" : "",
                    'PATH' => isset($this->extra_params['files']) && isset($this->extra_params['files'][$key]) ? $this->extra_params['file_' . $key]['path'] : "",
                ));
                if (isset($this->params['allow_edit']) && $this->params['allow_edit'] == "false")
                {
                    $this->tplobj->assign_block_vars("row.cell.text", array());
                }
                elseif (($field['type'] == "int" && $field['size'] == 1) || in_array($key, array_keys($this->extra_params['checkboxes'])))
                {
                    $this->tplobj->assign_block_vars("row.cell.checkbox", array());
                }
                elseif($field['type'] == "text" || $field['type'] == "mediumtext")
                {
                    if (isset($this->extra_params['readonly']) && in_array($key, array_keys($this->extra_params['readonly'])))
                    {
                        $this->tplobj->assign_block_vars("row.cell.text", array());
                    }
                    else
                    {
                        $this->tplobj->assign_block_vars("row.cell.textarea", array(
                            'PARAMS' => isset($this->extra_params['textareas']) && isset($this->extra_params['textareas'][$key]) ? $this->extra_params['textareas'][$key] : ""
                        ));
                    }
                }
                elseif (in_array($key, array_keys($this->extra_params['references'])))
                {
                    $method = $this->extra_params['references'][$key];
                    $this->tplobj->assign_block_vars('row.cell.select', array(
                        'OPTIONS' => HTML::options_by_array($row[$key], $this->$method(), 1)
                    ));
                }
                elseif (isset($this->extra_params['box_references']) && in_array($key, array_keys($this->extra_params['box_references'])))
                {
                    $method = $this->extra_params['box_references'][$key]."_data_line";
                    $this->tplobj->assign_block_vars("row.cell.boxlist", array());
                    if (method_exists($this, $method))
                    {
                        $this->$method($row[$this->table_info['primary_key']]);
                    }
                }
                elseif (isset($this->extra_params['files']) && isset($this->extra_params['files'][$key]) && isset($this->extra_params['file_' . $key]))
                {
                    $this->tplobj->assign_block_vars("row.cell.file", array());
                    if (file_exists($this->extra_params['file_'.$key]['path'].'/'.$row[$key]) && is_file($this->extra_params['file_'.$key]['path'].'/'.$row[$key]))
                    {
                        $this->tplobj->assign_block_vars("row.cell.file.has_image", array());
                    }
                }
                else
                {
                    if (isset($this->extra_params['readonly']) && in_array($key, array_keys($this->extra_params['readonly'])))
                    {
                        $this->tplobj->assign_block_vars("row.cell.text", array());
                    }
                    elseif(isset($this->extra_params['dates']) && in_array($key, array_keys($this->extra_params['dates'])))
                    {
                        $this->tplobj->assign_block_vars("row.cell.datebox", array());
                    }
                    else
                    {
                        $this->tplobj->assign_block_vars("row.cell.textbox", array());
                    }
                }
            }
            $index++;
            if ($this->params['allow_delete'] == 1)
            {
                $this->tplobj->assign_block_vars('row.delete', array());
            }
            if (isset($this->extra_params['add_action']))
            {
                foreach ($this->extra_params['add_action'] as $action)
                {
                    list($url, $text) = explode("|", $action);
                    $url = str_replace("%id", $row[$this->table_info['primary_key']], $url);
                    foreach ($this->table_info['fields'] as $key => $field)
                    {
                        $url = str_replace("%".$key, isset($row[$key."_original"]) ? $row[$key."_original"] : (isset($row[$key]) ? $row[$key] : ""), $url);
                        if (strpos($text, ":") !== false)
                        {
                            list($f, $t1, $t2) = explode(":", $text);
                            if (!empty($row[$f."_original"]))
                            {
                                $text = $t1;
                            }
                            else
                            {
                                $text = $t2;
                            }
                        }
                    }
                    $this->tplobj->assign_block_vars("row.action", array(
                        'URL' => $url,
                        'TEXT' => $text
                    ));    
                }
            }
        }
        if (isset($this->extra_params['delimiter_aggregate']) && is_array($this->extra_params['delimiter_aggregate']))
        {
            if (!empty($current_group))
            {
                $aggregate = array();
                foreach ($this->extra_params['delimiter_aggregate'] as $akey => $avalue)
                {
                    list($acaption, $afunc) = explode("|", $avalue);
                    $aggregate[] = $acaption .": " . (isset($current_group[$akey]) ? $current_group[$akey] : "");
                }
                //$last_delim_arr[0]['AGGREGATE'] = "(" . implode(",", $aggregate) . ")";
                //$this->tplobj->append_block_stub("row", "delimiter", array('AGGREGATE' => implode(", ", $aggregate)));
                $groups[] = array('AGGREGATE' => "(" . implode(", ", $aggregate) . ")");
                $current_group = array();
            }
            array_shift($groups);
            $this->tplobj->append_block_stub("row", "delimiter", $groups);
        }

    }
    
    private function process_table()
    {
        $id = mtoToolkit :: instance()->getRequest()->getInteger("id");
        if (isset($this->extra_params['config']) && isset($this->extra_params['config']['use_model']))
        {
            if ($id < 0)
            {
                $id = 0;
            }
            $className = $this->extra_params['config']['use_model'];
            $obj = new $className(intval($id));
            $obj->importAndSave(mtoHttpRequest :: instance()->export());
            return;
        }
        $row = array();
        $skip = array();
        if (!empty($id))
        {
            foreach ($this->table_info['fields'] as $key => $field)
            {
                if ($key != $this->table_info['primary_key'] && !isset($this->extra_params['file_' . $key]))
                {
                    if (isset($this->extra_params['checkboxes']) && array_key_exists($key, $this->extra_params['checkboxes']) || mtoToolkit :: instance()->getRequest()->has($key))
                    {
                        $row[$key] = mtoToolkit :: instance()->getRequest()->get($key);
                        //HTTP::fetch($key, $this->convert_type($field['type']), $row[$key], $this->get_req_default($field['type']), "post");
                    }
                }
                else
                {
                    if (isset($this->extra_params['file_'.$key]))
                    {
                        $row[$key] = mtoToolkit :: instance()->upload($key, $this->extra_params['file_'.$key]['path'] . "/!" . $this->extra_params['file_'.$key]['prefix'], $this->extra_params['file_'.$key]['validation']);
                        //$row[$key] = Common :: upload($key, $this->extra_params['file_'.$key]['path'], true, $this->extra_params['file_'.$key]['prefix'], $this->extra_params['file_'.$key]['validation']);
                        if ($row[$key])
                        {
                            $orig_row = $this->db->sql_getone("select * from " . $this->params['table'] . " where " . $this->table_info['primary_key'] . "=?", array($id));
                            if (file_exists($this->extra_params['file_'.$key]['path'].'/'.$orig_row[$key]) && is_file($this->extra_params['file_'.$key]['path'].'/'.$orig_row[$key]))
                            {
                                unlink($this->extra_params['file_'.$key]['path'].'/'.$orig_row[$key]);
                            }
                        }
                        else
                        {
                            $skip[] = $key;
                        }
                    }
                }
            }
            if (isset($this->extra_params['add_fields']) && is_array($this->extra_params['add_fields']))
            {
                foreach ($this->extra_params['add_fields'] as $field)
                {
                    unset($row[$field]);
                }
            }
            if (count($skip))
            {
                foreach ($skip as $s)
                {
                    unset($row[$s]);
                }
            }
            if (isset($this->extra_params['readonly']) && is_array($this->extra_params['readonly']))
            {
                foreach ($this->extra_params['readonly'] as $k => $v)
                {
                    unset($row[$k]);
                }
            }
            if ($id > 0)
            {
                $row[$this->table_info['primary_key']] = $id;
                $this->db->sql_update($this->params['table'], $row, $this->table_info['primary_key']);
            }
            else
            {
                $data = array();
                foreach ($row as $key => $value)
                {
                    if (!is_numeric($key) && !in_array($key, array("mode", "action")) && !preg_match("#^submit_#", $key))
                    {
                        $data[$key] = $value;
                    }
                }
                $id = $this->db->sql_insert($this->params['table'], $data);
            }
            if (isset($this->extra_params['box_references']) && is_array($this->extra_params['box_references']))
            {
                foreach ($this->extra_params['box_references'] as $func)
                {
                    $method = $func."_replace";
                    if (method_exists($this, $method))
                    {
                        $this->$method($id);
                    }
                }
            }
        }
    }
    
    private function process_delete($row_id)
    {
        if (!empty($row_id))
        {
            if (isset($this->extra_params['box_references']) && is_array($this->extra_params['box_references']))
            {
                foreach ($this->extra_params['box_references'] as $func)
                {
                    $method = $func."_delete";
                    if (method_exists($this, $method))
                    {
                        $this->$method($row_id);
                    }
                }
            }
            if (isset($this->extra_params['files']) && is_array($this->extra_params['files']))
            {
                $row = $this->db->sql_getone("select * from " . $this->params['table']." where " . $this->table_info['primary_key']."=?", array($row_id));
                foreach ($this->extra_params['files'] as $key => $file)
                {
                    if (file_exists($this->extra_params['file_'.$key]['path'].'/'.$row[$key]) && is_file($this->extra_params['file_'.$key]['path'].'/'.$row[$key]))
                    {
                        unlink($this->extra_params['file_'.$key]['path'].'/'.$row[$key]);
                    }
                }
            }
            $this->db->sql_query("delete from ".$this->params['table']." where ".$this->table_info['primary_key']."=?", array($row_id));
        }
    }
    
    private function convert_type($type)
    {
        switch ($type)
        {
            case "varchar":
            case "blob":
                return "str";
            break;
            case "int":
                return "int";
            break;
            case "float":
                return "float";
            break;
            default:
                return "str";
            break;
        }
    }
    
    private function get_req_default($type)
    {
        switch ($type)
        {
            case "varchar":
            case "blob":
            case "text":
            default:
                return "";
            break;
            case "int":
            case "float":
                return 0;
            break;
        }
    }
    
    private function preexec($type, $value, $row = array(), $key = null)
    {
        static $ticket = null;
        static $users = array();
        static $rownum = 0;

        
        switch ($type)
        {
            case "rownum":
                return ++$rownum;
            break;
            case "boolean":
                return $value ? "Да" : "Нет";
            break;
            case "datetime":
                if (!empty($value))
                {
                    return date("Y-m-d H:i:s", $value);
                }
                else
                {
                    return "";
                }
            break;
            case "date":
                if (!empty($value))
                {
                    return date("Y-m-d", $value);
                }
                else
                {
                    return "";
                }
            break;
            case "datetime":
                if (!empty($value))
                {
                    return date("Y-m-d H:i", $value);
                }
                else
                {
                    return "";
                }
            break;
            case "base64decode":
                return base64_decode($value);
            break;
//            case "boolean":
//                var_dump($value);
//                return ($value ? "Да" : "Нет");
//            break;
            case "ticket_status":
                $statuses = mtoConf :: instance()->env("ticket_status_options");
                foreach ($statuses as $status)
                {
                    if ($status['value'] == $value)
                    {
                        return $status['text'];
                    }
                }
                return "---";
            break;
            case "action_code_status":
                if (isset($GLOBALS['action_code_statuses'][$value]))
                {
                    return $GLOBALS['action_code_statuses'][$value];
                }
                else
                {
                    return "---";
                }
            break;
            case "balance_event_type":
                switch ($value)
                {
                    case User :: BALANCE_DEBIT:
                        return "Заказ";
                    break;
                    case User :: BALANCE_CREDIT:
                        return "Выплата";
                    break;
                    case User :: BALANCE_ADD_SALE:
                        return "Зачисление";
                    break;
                    case User :: BALANCE_ADD_PARTNER:
                        return "Зачисление партнеру";
                    break;
                    default:
                        return "Неизвестно";
                    break;
                }
            break;
            case "order_num":
                return "<a href='/orders/list/".$value."' target='_blank'>".$value."</a>";
            break;
            case "bill_num":
                return "<a href='/admin.php?mode=bill&action=card&bill_id=".$value."'>" . $value . "</a>";
            break;
            case "bill_files":
                if (!empty($value))
                {
                    return "<a href='/admin.php?mode=bill&action=card&bill_id=".$row['bill_id_original']."' style='font-size: 2em; font-weight: bold;'>" . $value . "</a>";
                }
                else
                {
                    return $value;
                }
            break;
            case "ticket_type":
                if (is_null($ticket))
                {
                    $ticket = new Ticket();
                    $ticket->load_types();
                }
                $types = $ticket->getTypes();
                if (isset($types[$value]))
                {
                    return $types[$value]['tt_name'];
                }
                else
                {
                    return "---";
                }
            break;
            case "gift_status":
                foreach (mtoConf :: getEnv('order_gift_status_options') as $status)
                {
                    if ($status['value'] == $value)
                    {
                        return $status['text'];
                    }
                }
                return "---";
            break;
            case "order_gift":
                $text = "";
                foreach (mtoConf :: getEnv('order_gifts') as $gift)
                {
                    if ($value == $gift['value'])
                    {
                        $text .= $gift['text'] . "<br />";
                    }
                }
                $text .= $row['order_gift_comment'];
                return $text;
            break;
            case "ticket_body":
            break;
            case "truncate":
                if (mb_strlen($value, 'UTF-8') > 100)
                {
                    return "<span  data-tooltip='".str_replace("'", "", str_replace("\r", "", str_replace("\n", "", nl2br($value))))."' title=''>" . mb_substr($value, 0, 100, 'UTF-8') . "..." . "</span>";
                }
                else
                {
                    return $value;
                }
            break;
            case "email":
                return "<a href='mailto:$value'>$value</a>";
            break;
            case "usercard":
                if (!empty($row) && !empty($key) && preg_match("#(\w+)\_login$#", $key, $matches) && isset($row[$matches[1]."_id"]))
                {
                    return "<a href='/admin.php?mode=users&action=usercard&uid=".$row[$matches[1]."_id"]."' target='_blank'>".$value."</a>";
                }
                elseif (!isset($users[$value]))
                {
                    $u = new User($value);
                    $users[$value] = $u;
                    return "<a href='/admin.php?mode=users&action=usercard&uid=".$value."' target='_blank'>".$users[$value]->get_login()."</a>";
                }
                elseif (isset($users[$value]))
                {
                    return "<a href='/admin.php?mode=users&action=usercard&uid=".$value."' target='_blank'>".$users[$value]->get_login()."</a>";
                }
            break;
            case "usercard_fio":
                if (!empty($row) && !empty($key) && preg_match("#(\w+)\_login$#", $key, $matches) && isset($row[$matches[1]."_id"]))
                {
                    return "<a href='/admin.php?mode=users&action=usercard&uid=".$row[$matches[1]."_id"]."' target='_blank'>".$value."</a>";
                }
                elseif (!isset($users[$value]))
                {
                    $u = new User($value);
                    $users[$value] = $u;
                    return "<a href='/admin.php?mode=users&action=usercard&uid=".$value."' target='_blank'>".$users[$value]->get_login()." (".$users[$value]->get_lastname()." " . $users[$value]->get_firstname() . ")</a>";
                }
                elseif (isset($users[$value]))
                {
                    return "<a href='/admin.php?mode=users&action=usercard&uid=".$value."' target='_blank'>".$users[$value]->get_login()." (".$users[$value]->get_lastname() . " " . $users[$value]->get_firstname() .")</a>";
                }
            break;
            case "user":
                if (!isset($users[$value]))
                {
                    $u = new User($value);
                    $users[$value] = $u;
                }
                return "<a href='/admin.php?mode=users&action=list&filter=".$users[$value]->get_login()."&submit_filter=1' target='_blank'>".$users[$value]->get_login()."</a>";
            break;
            case "store":
                return "<a href='http://".$value.".".mtoConf :: instance()->val("core|domain")."' target='_blank'>" . htmlspecialchars($value) . "</a>";
            break;
            case "orders_by_store":
                return "<a href='/admin.php?mode=orders&store=".$row['store_id']."' target='_blank'>" . $value . "</a>";
            break;
            case "products_by_store":
                return "<a href='/admin.php?mode=instance&action=products&store=".$row['store_id']."' target='_blank'>" . $value . "</a>";
            break;
            case "escape":
                return htmlspecialchars($value);
            break;
            case "aprrejgroup":
                return "<input type='radio' name='change_status[".$row['order_id']."]' value='".CommonPayment :: PMT_APPROVED."' />&nbsp;Одобрить<br /><input type='radio' name='change_status[".$row['order_id']."]' value='".CommonPayment :: PMT_REJECTED."' />&nbsp;Отклонить";
            break;
            case "users_for_manuf_type":
                return Task :: create()->getOwnersForType($row['type_id']);
            break;
            case "call_request_status":
                return CallRequest :: create(0)->getStatusName($value);
            break;
            case "call_request_period":
                return isset($GLOBALS['call_request_periods'][$value]) ? $GLOBALS['call_request_periods'][$value]['text'] : "---";
            break;
            case "front_cms_type":
                return isset($GLOBALS['front_cms_types'][$value]) ? $GLOBALS['front_cms_types'][$value]['text'] : "---";
            break;
            default:
                return $value;
            break;
        }
    }
    
    protected function reorder_columns()
    {
        $fields = $this->table_info['fields'];
        $this->table_info['fields'] = array();
        $new_fields = array();
        foreach ($this->extra_params['fields_order'] as $key => $value)
        {
            if (isset($fields[$key]))
            {
                $new_fields[$key] = $fields[$key];
                unset($fields[$key]);
            }
        }
        foreach ($fields as $key => $field)
        {
            $new_fields[$key] = $field;
        }
        $this->table_info['fields'] = $new_fields;
    }
    
    protected function append_table_info()
    {
        if (isset($this->extra_params['add_fields']))
        {
            foreach ($this->extra_params['add_fields'] as $field)
            {
                $this->table_info['fields'][$field] = array(
                    'name' => $field,
                    'type' => "dummy",
                    'size' => 0
                );
            }
        }
    }

    private function controller_options()
    {
        $array = array();
        $options = mtoClass :: lookup("classes/controllers", "*Controller", "performAction");
        if (is_array($options))
        {
            foreach ($options as $option)
            {
                if (defined($option."::CONTROLLER_NAME"))
                {
                    $array[] = array('value' => $option, 'text' => constant($option."::CONTROLLER_NAME"));
                }
            }
        }
        return $array;
    }

    private function action_code_status_options()
    {
        $array = array();
        foreach (mtoConf :: getEnv('action_code_statuses') as $k => $v)
        {
            $array[] = array('value' => $k, 'text' => $v);
        }
        return $array;
    }
    
    private function reason_type_options()
    {
        return mtoConf :: getEnv('reason_type_options');
    }

    private function front_cms_type_options()
    {
        return mtoConf :: getEnv('front_cms_types');
    }

    private function mc_calc_method_options()
    {
        return mtoConf :: instance()->env("mc_method_options");
    }
    
    private function country_options()
    {
        $array = array();
        $options = $this->db->sql_getall("select country_id, country_name from ".Dummy :: COUNTRY_TABLE." order by country_name");
        if (is_array($options))
        {
            foreach ($options as $option)
            {
                $array[] = array('value' => $option['country_id'], 'text' => $option['country_name']);
            }
        }
        return $array;
    }

    private function bill_status_options()
    {
        $array = array();
        foreach (mtoConf :: instance()->env("bill_statuses") as $k => $v)
        {
            $array[] = array('value' => $k, 'text' => $v);
        }
        return $array;
    }

    private function bill_priority_options()
    {
        $array = array();
        foreach (mtoConf :: instance()->env("bill_priorities") as $k => $v)
        {
            $array[] = array('value' => $k, 'text' => $v);
        }
        return $array;
    }

    private function bill_category_options()
    {
        $cats = $this->db->sql_getall("select cat_id, cat_name from ".Dummy :: CATALOG_TABLE." where cat_parent=? order by cat_left", array(Catalog :: ROOT_BILL_CATEGORY));
        $array = array();
        foreach ($cats as $cat)
        {
            $array[] = array('value' => $cat['cat_id'], 'text' => $cat['cat_name']);
        }
        return $array;
    }
    
    private function template_options()
    {
        $array = array();
        $list = mtoFs :: ls("templates/root", "f");
        foreach ($list as $file)
        {
            if (substr($file, -4) == ".tpl")
            {
                $array[] = array('value' => $file, 'text' => $file);
            }
        }
        return $array;
    }
    
    private function append_html_bill_urgent($row)
    {
        if ($row['bill_priority'] == Bill :: PRIORITY_URGENT)
        {
            return "<img src='/templates/images/icon_urgent.png' /><br />";
        }
    }
    
    private function ticket_notify_color($row, $color)
    {
        if (!empty($row['ticket_notify_date']) && time() > strtotime($row['ticket_notify_date']))
        {
            return "#" . TICKET_NOTIFY_COLOR;
        }
        else
        {
            return $color;
        }
    }

    private function ticket_tovar_link_color($row, $color)
    {
        if (!empty($row['ticket_tovar_link']))
        {
            return "yellow";
        }
        else
        {
            return $color;
        }
    }

    private function store_locked_color($row, $color)
    {
        if ($row['store_locked'] == 1)
        {
            return "red";
        }
        else
        {
            return $color;
        }
    }
    
    
    private function region_link_insert_line($prefix = "")
    {
        $types = $this->region_link_delivery_types();
        foreach ($types as $type)
        {
            $this->tplobj->assign_block_vars($prefix . "insert.icell.boxlist.cbxline", array(
                'VALUE' => $type['delivery_id'],
                'CHECKED' => "",
                'CAPTION' => $type['delivery_name']
            ));
        }
    }
    
    private function region_link_data_line($key)
    {
        $types = $this->region_link_delivery_types();
        $data = $this->region_link_get_links($key);
        foreach ($types as $type)
        {
            $this->tplobj->assign_block_vars("row.cell.boxlist.cbxline", array(
                'VALUE' => $type['delivery_id'],
                'CHECKED' => in_array($type['delivery_id'], $data) ? "checked" : "",
                'CAPTION' => $type['delivery_name']
            ));
        }
    }
    
    private function region_link_delivery_types()
    {
        static $types = array();
        if (empty($types))
        {
            $types = $this->db->sql_getall("select * from ".Dummy :: DELIVERY_TYPE_TABLE." order by delivery_name", array(), array(), false, "delivery_id");
        }
        return $types;
    }
    
    private function region_link_get_links($key)
    {
        static $raw_data = array();
        if (empty($raw_data))
        {
            $data = $this->db->sql_getall("select * from ".Dummy :: GEO_DELIVERY_TABLE." where link_region > 0");
            if (is_array($data))
            {
                foreach ($data as $line)
                {
                    if (!isset($raw_data[$line['link_region']]))
                    {
                        $raw_data[$line['link_region']] = array();
                    }
                    $raw_data[$line['link_region']][] = $line['link_delivery'];
                }
            }
        }
        return isset($raw_data[$key]) ? $raw_data[$key] : array();
    }
    
    private function region_link_delete($key)
    {
        $this->db->sql_query("delete from ".Dummy :: GEO_DELIVERY_TABLE." where link_region=?", array($key));
    }
    
    private function region_link_replace($key)
    {
        $this->db->sql_query("delete from ".Dummy :: GEO_DELIVERY_TABLE." where link_region=?", array($key));
        $types = mtoToolkit :: instance()->getRequest()->getArray("region_delivery");
        foreach ($types as $type)
        {
            $this->db->sql_query("insert into ".Dummy :: GEO_DELIVERY_TABLE." (link_country, link_region, link_delivery) values (0, ?, ?)", array($key, $type));
        }
    }
    
    
    private function country_link_insert_line($prefix = "")
    {
        $types = $this->region_link_delivery_types();
        foreach ($types as $type)
        {
            $this->tplobj->assign_block_vars($prefix . "insert.icell.boxlist.cbxline", array(
                'VALUE' => $type['delivery_id'],
                'CHECKED' => "",
                'CAPTION' => $type['delivery_name']
            ));
        }
    }
    
    private function country_link_data_line($key)
    {
        $types = $this->region_link_delivery_types();
        $data = $this->country_link_get_links($key);
        foreach ($types as $type)
        {
            $this->tplobj->assign_block_vars("row.cell.boxlist.cbxline", array(
                'VALUE' => $type['delivery_id'],
                'CHECKED' => in_array($type['delivery_id'], $data) ? "checked" : "",
                'CAPTION' => $type['delivery_name']
            ));
        }
    }
    
    private function country_link_get_links($key)
    {
        static $raw_data = array();
        if (empty($raw_data))
        {
            $data = $this->db->sql_getall("select * from ".Dummy :: GEO_DELIVERY_TABLE." where link_country > 0");
            if (is_array($data))
            {
                foreach ($data as $line)
                {
                    if (!isset($raw_data[$line['link_country']]))
                    {
                        $raw_data[$line['link_country']] = array();
                    }
                    $raw_data[$line['link_country']][] = $line['link_delivery'];
                }
            }
        }
        return isset($raw_data[$key]) ? $raw_data[$key] : array();
    }
    
    private function country_link_delete($key)
    {
        $this->db->sql_query("delete from ".Dummy :: GEO_DELIVERY_TABLE." where link_country=?", array($key));
    }
    
    private function country_link_replace($key)
    {
        $this->db->sql_query("delete from ".Dummy :: GEO_DELIVERY_TABLE." where link_country=?", array($key));
        $types = mtoToolkit :: instance()->getRequest()->getArray("country_delivery");
        foreach ($types as $type)
        {
            $this->db->sql_query("insert into ".Dummy :: GEO_DELIVERY_TABLE." (link_country, link_region, link_delivery) values (?, 0, ?)", array($key, $type));
        }
    }    
}