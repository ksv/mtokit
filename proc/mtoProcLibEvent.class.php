<?php
mtoClass :: import("mtokit/thread/mtoLibEventBasic.class.php");
mtoClass :: import("mtokit/thread/mtoShell.class.php");

class mtoLibEvent extends mtoLibEventBasic
{

    public $resource;
    public $base;
    
    public function __construct()
    {
        parent::__construct();
        if (!$this->resource = event_new())
        {
            throw new mtoException('Can\'t create new event resourse (event_new)', 1);
        }
    }

    public function add($timeout = -1)
    {
        $this->checkResourse();
        if (!event_add($this->resource, $timeout))
        {
            throw new mtoException("Can't add event (event_add)", 1);
        }
        return $this;
    }

    public function del()
    {
        $this->checkResourse();
        if (!event_del($this->resource))
        {
            throw new mtoException("Can't delete event (event_del)", 1);
        }
        return $this;
    }

    public function setBase($event_base)
    {
        $this->checkResourse();
        $event_base->checkResourse();
        if (!event_base_set($this->resource, $event_base->resource))
        {
            throw new mtoException('Can\'t set event base (event_base_set)', 1);
        }
        return parent::setBase($event_base);
    }

    public function free()
    {
        if ($this->resource)
        {
            event_free($this->resource);
            $this->resource = null;
            parent::free();
        }
        return $this;
    }

    public function set($fd, $events, $callback, $arg = null)
    {
        $this->checkResourse();
        if (!event_set($this->resource, $fd, $events, $callback, array($this, $arg)))
        {
            throw new mtoException("Can't prepare event (event_set)", 1);
        }
        return $this;
    }

    public function setSignal($signo, $callback, $persist = true, $arg = null)
    {
        $this->checkResourse();
        $events = EV_SIGNAL;
        if ($persist)
        {
            $events |= EV_PERSIST;
        }
        if (!event_set($this->resource, $signo, $events, $callback, array($this, $arg, $signo)))
        {
            $name = mtoShell::signalName($signo);
            throw new mtoException("Can't prepare event (event_set) for $name ($signo) signal", 1);
        }
        return $this;
    }

    public function setTimer($callback, $arg = null)
    {
        $this->checkResourse();
        if (!event_timer_set($this->resource, $callback, array($this, $arg)))
        {
            throw new mtoException("Can't prepare event (event_timer_set) for timer", 1);
        }
        return $this;
    }

}
