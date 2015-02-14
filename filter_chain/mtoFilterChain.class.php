<?php
mtoClass :: import("mtokit/filter_chain/mtoInterceptingFilter.interface.php");


class mtoFilterChain implements mtoInterceptingFilter
{
    protected $filters = array();
    protected $counter = -1;


    function registerFilter($classpath, $args = array())
    {
        mtoClass :: import($classpath);
        $class = $this->getClassName($classpath);
        $filter = new $class($args);
        $this->filters[] = $filter;
    }

    function getFilters()
    {
        return $this->filters;
    }

    function next()
    {
        $this->counter++;

        if(isset($this->filters[$this->counter]))
        {
            $this->filters[$this->counter]->run($this);
        }
    }

    function process()
    {
        $this->counter = -1;
        $this->next();
    }

    function run($filter_chain)
    {
        $this->process();
        $filter_chain->next();
    }

    private function getClassName($classpath)
    {
        return str_replace(".class.php", "", basename($classpath));
    }
}


