<?php

abstract class mtoAbstractTools
{

    protected $reserved_methods = array('__construct', '_setRaw', '_getRaw');
    protected $toolkit;

    function __construct()
    {
        $this->toolkit = mtoToolkit :: instance();
    }

    function getToolsSignatures()
    {
        $methods = get_class_methods(get_class($this));

        $signatures = array();
        foreach ($methods as $method)
        {
            if (in_array($method, $this->reserved_methods))
                continue;
            $signatures[$method] = $this;
        }

        return $signatures;
    }

    protected function _setRaw($var, $value)
    {
        $this->toolkit->setRaw($var, $value);
    }

    protected function _getRaw($var)
    {
        return $this->toolkit->getRaw($var);
    }

}

