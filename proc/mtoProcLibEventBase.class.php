<?php

mtoClass :: import("mtokit/thread/mtoShell.class.php");
mtoClass :: import("mtokit/thread/mtoLibEvent.class.php");

class mtoLibEventBase
{
    const MAX_PRIORITY = 30;
    private static $counter = 0;
    public $id;
    public $resource;
    public $events = array();
    public $timers = array();

    public function __construct($init_priority = true)
    {
        if (!mtoShell::hasLibevent())
        {
            throw new mtoException('You need to install PECL extension "Libevent" to use this class', 1);
        }
        if (!$this->resource = event_base_new())
        {
            throw new mtoException('Can\'t create event base resourse (event_base_new)', 1);
        }
        $this->id = ++self::$counter;
        if ($init_priority)
        {
            $this->priorityInit();
        }
    }

    public function __destruct()
    {
        if ($this->resource)
        {
            $this->free();
        }
    }

    public function setEvent($event)
    {
        $event->setBase($this);
        return $this;
    }
    
    public function free()
    {
        if ($this->resource)
        {
            foreach ($this->events as $e)
            {
                $e->free();
            }
            @event_base_free($this->resource);
            $this->resource = null;
        }
        return $this;
    }

    public function loop($flags = 0)
    {
        $this->checkResourse();
        $res = event_base_loop($this->resource, $flags);
        if ($res === -1)
        {
            throw new mtoException('Can\'t start base loop (event_base_loop)', 1);
        }
        return $res;
    }

    public function loopBreak()
    {
        $this->checkResourse();
        if (!event_base_loopbreak($this->resource))
        {
            throw new mtoException('Can\'t break loop (event_base_loopbreak)', 1);
        }
        return $this;
    }

    public function loopExit($timeout = -1)
    {
        $this->checkResourse();
        if (!event_base_loopexit($this->resource, $timeout))
        {
            throw new mtoException('Can\'t set loop exit timeout (event_base_loopexit)', 1);
        }
        return $this;
    }

    public function priorityInit($value = self::MAX_PRIORITY)
    {
        $this->checkResourse();
        if (!event_base_priority_init($this->resource, ++$value))
        {
            $msg = "Can't set the maximum priority level of the event base to $value (event_base_priority_init)";
            throw new mtoException($msg, 1);
        }
        return $this;
    }

    public function checkResourse()
    {
        if (!$this->resource)
        {
            throw new mtoException('Can\'t use event base resource. It\'s already freed.', 2);
        }
    }

    public function timerAdd($name, $interval = null, $callback = null, $arg = null, $start = true, $q = 1000000)
    {
        $notExists = !isset($this->timers[$name]);

        if (($notExists || $callback) && !is_callable($callback, false, $callableName))
        {
            throw new mtoException("Incorrect callback [$callableName] for timer ($name).", 1);
        }

        if ($notExists)
        {
            $event = new mtoLibEvent();
            $event->setTimer(array($this, '_onTimer'), $name)
                    ->setBase($this);
            $this->timers[$name] = array(
                'name' => $name,
                'callback' => $callback,
                'event' => $event,
                'interval' => $interval,
                'arg' => $arg,
                'q' => $q,
                'i' => 0,
            );
        }
        else
        {
            $timer = &$this->timers[$name];
            $event = $timer['event'];
            $event->del();
            if ($callback)
            {
                $timer['callback'] = $callback;
            }
            if ($interval > 1)
            {
                $timer['interval'] = $interval;
            }
            if ($arg !== null)
            {
                $timer['arg'] = $arg;
            }
            $timer['i'] = 0;
        }

        if ($start)
        {
            $this->timerStart($name);
        }
    }

    public function timerStart($name, $interval = null, $arg = null, $resetIteration = true)
    {
        if (!isset($this->timers[$name]))
        {
            throw new mtoException("Unknown timer \"$name\". Add timer before using.", 1);
        }
        $timer = &$this->timers[$name];
        if ($resetIteration)
        {
            $timer['i'] = 0;
        }
        if ($arg !== null)
        {
            $timer['arg'] = $arg;
        }
        if ($interval > 1)
        {
            $timer['interval'] = $interval;
        }
        /** @var $event CLibEvent */
        $event = $timer['event'];
        $event->add($timer['interval'] * $timer['q']);
    }

    public function timerStop($name)
    {
        if (!isset($this->timers[$name]))
        {
            return;
        }
        $timer = &$this->timers[$name];
        /** @var $event CLibEvent */
        $event = $timer['event'];
        $event->del();
        $timer['i'] = 0;
    }

    public function timerDelete($name)
    {
        if (!isset($this->timers[$name]))
        {
            return;
        }
        $timer = &$this->timers[$name];
        /** @var $event CLibEvent */
        $event = $timer['event'];
        $event->free();
        unset($this->timers[$name]);
    }

    public function timerExists($name)
    {
        return isset($this->timers[$name]);
    }

    public function _onTimer($fd, $event, $args)
    {
        $name = $args[1];

        // Skip deleted timers
        if (!isset($this->timers[$name]))
        {
            return;
        }

        // Invoke callback
        $timer = &$this->timers[$name];
        $res = call_user_func($timer['callback'], $this, $name, ++$timer['i'], $timer['arg']);
        if ($res)
        {
            $this->timerStart($name, null, null, false);
        }
        else
        {
            $timer['i'] = 0;
        }
    }

}
