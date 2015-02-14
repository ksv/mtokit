<?php

class mtoRegistry
{

    protected static $cache = array();

    static function set($name, $value)
    {
        self :: $cache[$name][0] = $value;
    }

    static function get($name)
    {
        if (isset(self :: $cache[$name][0]))
            return self :: $cache[$name][0];
    }

    static function save($name)
    {
        if (isset(self :: $cache[$name]))
            array_unshift(self :: $cache[$name], array());
        else
            throw new Exception("No such registry entry '$name'");
    }

    static function restore($name)
    {
        if (isset(self :: $cache[$name]))
            array_shift(self :: $cache[$name]);
        else
            throw new lmbException("No such registry entry '$name'");
    }

}

