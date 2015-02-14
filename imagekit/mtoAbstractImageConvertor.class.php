<?php

abstract class mtoAbstractImageConvertor
{

    protected $container = null;
    protected $params;

    function __construct($params = array())
    {
        $this->params = $params;
    }

    function __call($name, $args)
    {
        $params = (isset($args[0]) && is_array($args[0])) ? $args[0] : array();
        return $this->applyFilter($name, $params);
    }

    protected function applyFilter($name, $params)
    {
        $filter = $this->createFilter($name, $params);
        $filter->apply($this->container);
        return $this;
    }

    function getContainer()
    {
        return $this->container;
    }
    
    function getResource()
    {
        return $this->container->getResource();
    }
    
    function load($file_name, $type = '')
    {
        $this->container = $this->createImageContainer($file_name, $type);
        return $this;
    }

    function apply($name)
    {
        $args = func_get_args();
        $params = (isset($args[1]) && is_array($args[1])) ? $args[1] : array();
        return $this->applyFilter($name, $params);
    }

    function applyBatch($batch)
    {
        foreach ($batch as $filter)
        {
            list($name, $params) = each($filter);
            $this->applyFilter($name, $params);
        }
        return $this;
    }

    function save($file_name = null, $type = '', $quality = null)
    {
        if ($type)
            $this->container->setOutputType($type);
        $this->container->save($file_name, $quality);
        $this->container = null;
        return $this;
    }

    protected function loadFilter($name, $prefix)
    {
        $class = 'mto' . $prefix . mto_camel_case($name) . 'ImageFilter';
        mtoClass :: import('mtokit/imagekit/'.strtolower($prefix).'/filters/' . $class . '.class.php');
        return $class;
    }

    abstract protected function createFilter($name, $params);

    abstract protected function createImageContainer($file_name, $type = '');

    abstract function isSupportConversion($file, $src_type = '', $dest_type = '');
}
