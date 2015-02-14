<?php
class mtoProfilerTimer extends mtoProfilerTool
{
    protected $request_time;
    protected $microtime;
    protected $own_time;
    protected $uri;
    protected $ip;
    protected $mode;
    protected $action;
    protected $method;
    protected $userName;
    protected $checkpoints = array();
    protected $pinba = array();
    protected $trace = null;
    protected $trace_timer = 0;
    protected $trace_current_timer = 0;

    function __construct(mtoProfiler $profiler)
    {
        parent :: __construct($profiler);
        $request = $profiler->getRequest();
        $this->request_time = time();
        $this->microtime = microtime(true);
        if ($request)
        {
            $this->uri = $request->getServer("REQUEST_URI");
            $this->method = $request->getServer("REQUEST_METHOD");
            $this->ip = $request->getServer("REMOTE_ADDR");
            $this->mode = $request->get("mode");
            $this->action = $request->get("action");
        }
    }

    function start_checkpoint($name)
    {
        if (!isset($this->checkpoints[$name]))
        {
            $this->checkpoints[$name] = array('name' => $name, 'time' => microtime(true), 'duration' => 0, 'type' => "simple");
        }
        else
        {
            $this->checkpoints[$name]['time'] = microtime(true);
            $this->checkpoints[$name]['duration'] = 0;
        }
    }

    function add_checkpoint($name)
    {
        return $this->start_checkpoint($name);
    }

    function start_increment_checkpoint($name)
    {
        if (!isset($this->checkpoints[$name]))
        {
            $this->checkpoints[$name] = array('name' => $name, 'time' => microtime(true), 'duration' => 0, 'type' => "increment");
        }
        else
        {
            $this->checkpoints[$name]['time'] = microtime(true);
        }
    }
    
    function start_count_checkpoint($name)
    {
        if (!isset($this->checkpoints[$name]))
        {
            $this->checkpoints[$name] = array('name' => $name, 'time' => 0, 'duration' => 1, 'type' => "count");
        }
        else
        {
            $this->checkpoints[$name]['duration']++;
        }
    }

    function end_checkpoint($name)
    {
        if (isset($this->checkpoints[$name]))
        {
            $this->checkpoints[$name]['duration'] = microtime(true) - $this->checkpoints[$name]['time'];
        }
    }
    
    function end_increment_checkpoint($name)
    {
        if (isset($this->checkpoints[$name]))
        {
            $this->checkpoints[$name]['duration'] += (microtime(true) - $this->checkpoints[$name]['time']);
        }
    }

    function end_any_checkpoint($name)
    {
        if (!isset($this->checkpoints[$name]))
        {
            return;
        }
        switch ($this->checkpoints[$name]['type'])
        {
            case "simple":
                $this->end_checkpoint($name);
            break;
            case "increment":
                $this->end_increment_checkpoint($name);
            break;
        }
    }
    
    function start_pinba_checkpoint($name, $tags)
    {
        if (extension_loaded("pinba"))
        {
            $this->pinba[$name] = pinba_timer_start($tags);
        }
    }
    
    function end_pinba_checkpoint($name)
    {
        if (extension_loaded("pinba"))
        {
            pinba_timer_stop($this->pinba[$name]);
        }
    }
    
    function get_all_checkpoints()
    {
        return $this->checkpoints;
    }

    function trace($message, $time = null)
    {
        if (is_null($this->trace))
        {
            if (is_null($this->trace))
            {
                if ($time)
                {
                    $this->trace_timer = $time;
                    $this->trace_current_timer = $time;
                }
                else
                {
                    $this->trace_timer = microtime(true);
                    $this->trace_current_timer = microtime(true);
                }
                $this->trace = array();
            }
        }
        $this->trace[] = array('msg' => $message, 'from_prev' => round(microtime(true)-$this->trace_current_timer, 3), 'from_start' => round(microtime(true)-$this->trace_timer, 3));
        $this->trace_current_timer = microtime(true);
    }

    function get_trace()
    {
        $this->trace("all request");
        return $this->trace;
    }


}