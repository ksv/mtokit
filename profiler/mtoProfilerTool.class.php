<?php
abstract class mtoProfilerTool
{
    protected $profiler = null;

    function __construct(mtoProfiler $profiler)
    {
        $this->profiler = $profiler;
    }
}