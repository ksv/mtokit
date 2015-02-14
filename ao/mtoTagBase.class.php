<?php
abstract class mtoTagBase extends mtoActiveObject
{


    public  function getOrCreateByName($title)
    {
        $title = trim($title);
        if (!empty($title))
        {
            $model = mto_camel_case($this->_model_name);
            $existing_tag = $this->_db -> sql_getone("select *  from ".$this->_table_name." where ".$this->_prefix."_title=?", array($title));
            if (!$existing_tag)
            {
                $translit_title = mtoToolkit::instance()->translit_russian($title);
                $obj = new  $model();
                $obj->set('title',$title);
                $obj->set('translit_title',$translit_title);
                $obj->save();
                return $obj;


            } else
            {
                return new  $model($existing_tag[$this->_prefix.'_id']);
            }
        }

    }

    public  function findByName($title)
    {
        
        $existing_tag = $this->_db -> sql_getone("select $this->_pm_key  from ".$this->_table_name." where ".$this->_prefix."_title = ?", array($title));
        if ($existing_tag)
        {
            return $existing_tag[$this->_pm_key];
        }

        return false;

    }//


    
    
}