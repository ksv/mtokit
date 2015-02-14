<?php

require_once("mtokit/ao/mtoActiveObjectSetInterface.interface.php");

class mtoActiveObjectContainer implements mtoActiveObjectSetInterface
{

    private $_map = array(
        'public' => array(),
        'dynamic' => array(),
        'initialized' => false,
    );

    /**
     * Constructor.
     * Fills internals properties if any
     * @param array properties array
     */
    function __construct($properties = array())
    {
        $this->_registerPredefinedVariables();

        if ($properties)
        {
            $this->import($properties);
        }
    }

    protected function _registerPredefinedVariables()
    {
        if ($this->_map['initialized'])
        {
            return;
        }

        $var_names = get_object_vars($this);
        foreach ($var_names as $key => $item)
        {
            if (!$this->_isGuarded($key))
            {
                $this->_map['public'][$key] = $key;
            }
        }

        $this->_map['initialized'] = true;
    }

    /**
     * Returns class name using PHP built in get_class
     * @see get_class
     * @return string
     */
    final function getClass()
    {
        return get_class($this);
    }

    /**
     * Merges existing properties with new ones
     * @param array
     */
    function import($values)
    {
        if (!is_array($values))
        {
            return;
        }

        foreach ($values as $property => $value)
        {
            $this->_setRaw($property, $value);
        }
    }

    /**
     * Exports all object properties as an array
     * @return array
     */
    function export()
    {
        $exported = array();
        foreach ($this->getPropertiesNames() as $name)
        {
            $exported[$name] = $this->$name;
        }

        return $exported;
    }

    /**
     * Checks if such property exists
     * Can be overridden in child classes 
     * @return bool returns true even if attribute is null
     */
    function has($name)
    {
        return $this->_hasProperty($name) || $this->_mapPropertyToMethod($name);
    }

    protected function _hasProperty($name)
    {
        $this->_registerPredefinedVariables();
        return isset($this->_map['public'][$name]);
    }

    function getPropertiesNames()
    {
        $this->_registerPredefinedVariables();
        return array_keys($this->_map['public']);
    }

    /**
     * Alias for getPropertiesNames
     *
     * @deprecated
     */
    function getAttributesNames()
    {
        return $this->getPropertiesNames();
    }

    /**
     * Removes specified property
     * @param string
     */
    function remove($name)
    {
        if ($this->_isGuarded($name))
        {
            return;
        }

        unset($this->_map['public'][$name]);
        unset($this->_map['dynamic'][$name]);
        unset($this->$name);
    }

    /**
     * Removes all object properties
     */
    function reset()
    {
        $this->_map['public'] = array();
        $this->_map['dynamic'] = array();
        foreach ($this->getPropertiesNames() as $name)
        {
            unset($this->$name);
        }
    }

    /**
     * Returns property value if it exists and not guarded.
     * Magically maps getter to fine-grained method if it exists, e.g. get('foo') => getFoo()
     * @param string property name
     * @return mixed|null
     */
    function get($name, $default = null)
    {
        if ($method = $this->_mapPropertyToMethod($name))
        {
            return $this->$method();
        }

        if ($this->_hasProperty($name))
        {
            return $this->_getRaw($name);
        }

        if ($default != null)
        {
            return $default;
        }

        throw new mtoActiveObjectCoreException("No such property '$name' in " . get_class($this));
    }

    /**
     * Sets property value
     * Magically maps setter to fine-grained method if it exists, e.g. set('foo', $value) => setFoo($value)
     * @param string property name
     * @param mixed value
     */
    function set($property, $value)
    {
        if (!$property)
        {
            return;
        }

        if ($method = $this->_mapPropertyToSetMethod($property))
        {
            return $this->$method($value);
        }

        $this->_setRaw($property, $value);
    }

    protected function _getRaw($name)
    {
        if ($this->_hasProperty($name))
        {
            return $this->$name;
        }
    }

    protected function _setRaw($name, $value)
    {
        if ($this->_isGuarded($name))
        {
            return;
        }

        $this->_map['public'][$name] = $name;
        $this->_map['dynamic'][$name] = $name;
        $this->$name = $value;
    }

    protected function _isGuarded($property)
    {
        return isset($property{0}) ? $property{0} == '_' : false;
    }

    /*     * #@+
     * Implements ArrayAccess interface
     * @see ArrayAccess
     */

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

    /*     * #@- */

    function __call($method, $args = array())
    {
        if ($property = $this->_mapGetToProperty($method))
        {
            if ($this->has($property))
            {
                return $this->get($property);
            }
        }
        elseif ($property = $this->_mapSetToProperty($method))
        {
            $this->set($property, $args[0]);
            return;
        }
        _D(debug_backtrace(), true);
        throw new mtoActiveObjectCoreException("No such method '$method' in " . get_class($this));
    }

    protected function _mapGetToProperty($method)
    {
        if (0 === strpos($method, 'get'))
        {
            return mto_under_scores(substr($method, 3));
        }
    }

    protected function _mapSetToProperty($method)
    {
        if (0 === strpos($method, 'set'))
        {
            return mto_under_scores(substr($method, 3));
        }
    }

    protected function _mapPropertyToMethod($property)
    {
        static $map = array();
        if (isset($map[$property]))
        {
            return $map[$property];
        }

        $capsed = mto_camel_case($property);
        $method = 'get' . $capsed;
        if ($method !== 'get' && method_exists($this, $method))
        {
            $map[$property] = $method;
            return $method;
        }
        //'is_foo' property is mapped to 'isFoo' method if it exists
        if (strpos($property, 'is_') === 0 && method_exists($this, $capsed))
        {
            $map[$property] = $capsed;
            return $capsed;
        }
        $map[$property] = false;
    }

    protected function _mapPropertyToSetMethod($property)
    {
        $method = 'set' . mto_camel_case($property);
        if ($method !== 'set' && method_exists($this, $method))
        {
            return $method;
        }
    }

    /**
     * __set  an alias of set()
     * @see set, offsetSet
     */
    function __set($property, $value)
    {
        if (isset($this->_map['dynamic'][$property]))
        {
            $this->$property = $value;
        }
        else
        {
            $this->set($property, $value);
        }
    }

    /**
     * __get -- an alias of get()
     * @see get,  offsetGet
     * @return mixed
     */
    function __get($property)
    {
        return $this->get($property);
    }

    /**
     * __isset  an alias of has()
     * @return boolean whether or not this object contains $name
     */
    function __isset($name)
    {
        return $this->has($name);
    }

    /**
     * __unser  an alias of remove()
     * @param string $name
     */
    function __unset($name)
    {
        return $this->remove($name);
    }


}
