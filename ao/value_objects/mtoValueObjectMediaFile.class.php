<?php
mtoClass :: import("mtokit/ao/value_objects/mtoValueObjectBase.class.php");

class mtoValueObjectMediaFile extends mtoValueObjectBase
{

    function get($args = array())
    {
        $obj = new Media($this->_owner->getId());
        return $obj->getThumbUrl($args['w']);
    }

    function cloneMe(mtoActiveObject $clon)
    {
    }

    function set($value)
    {
        
    }

    protected function parseArgs($name)
    {
        $parts = explode("_", $name);
        list($w, $h) = explode("x", array_shift($parts));
        return array(
            'w' => $w, 'h' => $h
        );
    }


}