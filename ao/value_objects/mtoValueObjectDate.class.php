<?php
mtoClass :: import("mtokit/ao/value_objects/mtoValueObjectBase.class.php");

class mtoValueObjectDate extends mtoValueObjectBase
{

    function get($args = array())
    {
        if (is_null($this->_value))
        {
            return null;
        }
        $value = empty($this->_value) ? time() : $this->_value;
        //var_dump($value);
        if (is_null($value))
        {
            //var_dump("null");
            return null;
        }
        return date("Y-m-d", $value);
    }

    function set($value)
    {
        if (is_numeric($value))
        {
            $this->_value = $value;
        }
        else
        {
            $this->_value = strtotime($value);
        }
    }


}