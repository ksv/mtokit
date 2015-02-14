<?php
mtoClass :: import("mtokit/filter_chain/mtoFilterChain.class.php");
mtoClass :: import("mtokit/filter_chain/mtoInterceptingFilter.interface.php");
class mtoWebApplication
{
    private $filters = array();

    function __construct($filters = array(), $args = array())
    {
        foreach ($filters as $filter)
        {
            $this->addFilter($filter);
        }
    }

    function addFilter($classpath, $args = array())
    {
        mtoClass :: import($classpath);
        $classname = $this->getClassName($classpath);
        $filter = new $classname($args);
        $this->filters[] = $filter;
    }

    function run()
    {
        $chain = new mtoFilterChain();
        foreach ($this->filters as $filter)
        {
            $chain->registerFilter($filter);
        }
        $chain->process();
    }

    function getClassName($classpath)
    {
        return str_replace(".class.php", "", basename($classpath));
    }
}