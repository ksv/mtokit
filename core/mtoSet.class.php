<?php
mtoClass :: import("mtokit/core/mtoSetInterface.interface.php");

class mtoSet implements mtoSetInterface
{

    protected $__data = array();

    
    function __construct($properties = array())
    {
        if (is_array($properties))
        {
            $this->import($properties);
        }
    }

    function get($name, $default = null)
    {
        return isset($this->__data[$name]) ? $this->__data[$name] : $default;
    }

    function getInteger($name, $default = 0)
    {
        return (int) $this->get($name, $default);
    }

    function getNumeric($name, $default = 0)
    {
        return (0 + $this->get($name, $default));
    }

    function getArray($name, $default = array())
    {
        if (!is_array($value = $this->get($name, $default)))
        {
            return array();
        }

        return $value;
    }

    function getFloat($name, $default = 0)
    {
        return (float) str_replace(',', '.', $this->get($name, $default));
    }

    function set($name, $value)
    {
        $this->__data[$name] = $value;
    }

    function remove($name)
    {
        unset($this->__data[$name]);
    }

    function removeAll()
    {
        $this->__data = array();
    }

    function reset()
    {
        return $this->removeAll();
    }

    function merge($values)
    {
        if (is_array($values) || ($values instanceof ArrayAccess))
        {
            foreach ($values as $name => $value)
            {
                $this->set($name, $value);
            }
        }
    }

    function import($values)
    {
        $this->merge($values);
    }

    function export()
    {
        $exported = array();
        foreach ($this->__data as $name => $var)
        {
            $exported[$name] = $var;
        }
        return $exported;
    }

    function has($name)
    {
        return isset($this->__data[$name]);
    }

    function isEmpty()
    {
        return empty($this->__data);
    }


    //ArrayAccess interface
    function offsetExists($offset)
    {
        return $this->has($offset);
    }

    function offsetGet($offset)
    {
        return $this->get($offset);
    }

    function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    function offsetUnset($offset)
    {
        $this->remove($offset);
    }

}

