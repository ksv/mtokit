<?php
class mtoActiveObjectValueObjectMapper
{
    protected $_owner = null;
    protected $_toolkit = null;
    protected $_db = null;
    protected $_objects = array();


    function __construct(mtoActiveObject $owner)
    {
        $this->_owner = $owner;
        $this->_toolkit = $this->_owner->getGuarded("toolkit");
        $this->_db = $this->_owner->getGuarded("toolkit");
    }

    function define()
    {
        $this->_objects = array();
        foreach ($this->_owner->getColumnsConfig() as $column => $conf)
        {
            if (isset($conf['value_object']))
            {
                $this->_objects[$column] = array('class' => $conf['value_object']);
            }
        }
    }

    function getObject($name)
    {
        $this->createObject($name);
        return $this->_objects[$name]['instance'];
    }

    function has($name)
    {
        return array_key_exists($name, $this->_objects);
    }

    function cloneAll(mtoActiveObject $clon)
    {
        foreach ($this->_objects as $key => $object)
        {
            $this->getObject($key)->cloneMe($clon);
        }
    }

    protected function createObject($name)
    {
        if (!isset($this->_objects[$name]['instance']))
        {
            $class = "mtoValueObject" . mto_camel_case($this->_objects[$name]['class']);
            mtoClass :: import("mtokit/ao/value_objects/" . $class . ".class.php");
            $this->_objects[$name]['instance'] = new $class();
            $this->_objects[$name]['instance']->setOwner($this->_owner);
        }
    }
}