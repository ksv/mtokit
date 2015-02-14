<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');
mtoClass :: import('mtokit/profiler/mtoProfiler.class.php');

class mtoCoreTools extends mtoAbstractTools
{
    protected $profiler = null;
    protected $config = null;

    function getProfiler()
    {
        if (is_null($this->profiler))
        {
            $this->profiler = new mtoProfiler();
        }
        return $this->profiler;
    }

    function setProfiler($profiler)
    {
        $this->profiler = $profiler;
    }

    function getConfig()
    {
        if (is_null($this->config))
        {
            $this->config = mtoConf :: instance();
        }
        return $this->config;
    }
}