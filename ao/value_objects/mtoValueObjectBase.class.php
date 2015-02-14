<?php
abstract class mtoValueObjectBase implements ArrayAccess
{
    protected $_value;
    protected $_owner;

    function __construct($value = null)
    {
        
    }

    function setOwner($owner)
    {
        $this->_owner = $owner;
    }

    function loadValue($field)
    {
        $this->_value = $this->_owner->get($field);
    }

    function saveValue($field)
    {
        $this->_owner->set($field, $this->_value);
    }

    abstract function get($args = array());
    abstract function set($value);

    protected function parseArgs($name)
    {
        return array();
    }

    function offsetExists($offset)
    {
        return true;
        //return $this->has($offset);
    }

    function offsetGet($offset)
    {
        return $this->get($this->parseArgs($offset));
        //return $this->get($offset);
    }

    function offsetSet($offset, $value)
    {
        $this->set($value);
        //$this->set($offset, $value);
    }

    function offsetUnset($offset)
    {
        //$this->remove($offset);
    }


}