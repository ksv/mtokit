<?php

mtoClass :: import('mtokit/toolkit/mtoRegistry.class.php');

class mtoToolkit
{

    static protected $_instance = null;
    protected $_tools = array();
    protected $_tools_signatures = array();
    protected $_signatures_loaded = false;
    protected $_id;

    function __construct()
    {
        $this->_id = uniqid();
    }

    static function instance()
    {
        if (is_object(self :: $_instance))
            return self :: $_instance;

        self :: $_instance = new mtoToolkit();
        return self :: $_instance;
    }

    protected function setTools($tools)
    {
        if (!is_array($tools))
            $this->_tools = array($tools);
        else
            $this->_tools = $tools;

        $this->_tools_signatures = array();
        $this->_signatures_loaded = false;
    }

    static function setup($tools)
    {
        $toolkit = mtoToolkit :: instance();
        $toolkit->setTools($tools);

        return $toolkit;
    }

    static function save()
    {
        $toolkit = mtoToolkit :: instance();

        $tools = $toolkit->_tools;
        $tools_copy = array();
        foreach ($toolkit->_tools as $tool)
            $tools_copy[] = clone($tool);

        mtoRegistry :: set('__tools' . $toolkit->_id, $tools);
        mtoRegistry :: save('__tools' . $toolkit->_id);
        $toolkit->setTools($tools_copy);

        mtoRegistry :: set('__props' . $toolkit->_id, $toolkit->export());
        mtoRegistry :: save('__props' . $toolkit->_id);

        return $toolkit;
    }

    static function restore()
    {
        $toolkit = mtoToolkit :: instance();

        mtoRegistry :: restore('__tools' . $toolkit->_id);
        $tools = mtoRegistry :: get('__tools' . $toolkit->_id);
        mtoRegistry :: restore('__props' . $toolkit->_id);
        $props = mtoRegistry :: get('__props' . $toolkit->_id);

        if ($props !== null)
        {
            $toolkit->reset();
            $toolkit->import($props);
        }

        if ($tools !== null)
            $toolkit->setTools($tools);

        return $toolkit;
    }

    static function merge($tool)
    {
        $toolkit = mtoToolkit :: instance();
        $toolkit->add($tool);
        return $toolkit;
    }

    function add($tool)
    {
        $tools = $this->_tools;
        array_unshift($tools, $tool);
        $this->setTools($tools);
    }

    function set($var, $value)
    {
        if ($method = $this->_mapPropertyToSetMethod($var))
            return $this->$method($value);
        else
            return parent :: set($var, $value);
    }

    function get($var, $default = "djkhhwdjhejhd")
    {
        if ($method = $this->_mapPropertyToGetMethod($var))
            return $this->$method();
        else
            return parent :: get($var, $default);
    }

    function has($var)
    {
        return $this->_hasGetMethodFor($var) || parent :: has($var);
    }

    function setRaw($var, $value)
    {
        return parent :: _setRaw($var, $value);
    }

    function getRaw($var)
    {
        return parent :: _getRaw($var);
    }

    function __call($method, $args = array())
    {
        $this->_ensureSignatures();

        if (isset($this->_tools_signatures[$method]))
            return call_user_func_array(array($this->_tools_signatures[$method], $method), $args);

        throw new Exception("No such method '$method' exists in mtoToolkit");
    }

    protected function _ensureSignatures()
    {
        if ($this->_signatures_loaded)
            return;

        $this->_tools_signatures = array();
        foreach ($this->_tools as $tool)
        {
            $signatures = $tool->getToolsSignatures();
            foreach ($signatures as $method => $obj)
            {
                if (!isset($this->_tools_signatures[$method]))
                    $this->_tools_signatures[$method] = $obj;
            }
        }

        $this->_signatures_loaded = true;
    }

    protected function _hasGetMethodFor($property)
    {
        $this->_ensureSignatures();

        $capsed = mto_camel_case($property);
        $method = 'get' . $capsed;
        return isset($this->_tools_signatures[$method]);
    }

    protected function _mapPropertyToGetMethod($property)
    {
        $this->_ensureSignatures();

        $capsed = mto_camel_case($property);
        $method = 'get' . $capsed;
        if (isset($this->_tools_signatures[$method]))
            return $method;
    }

    protected function _mapPropertyToSetMethod($property)
    {
        $this->_ensureSignatures();

        $method = 'set' . mto_camel_case($property);
        if (isset($this->_tools_signatures[$method]))
            return $method;
    }

}

