<?php

mtoClass :: import("mtokit/thread/mtoShell.class.php");

abstract class mtoLibEventBasic
{

    private static $counter = 0;
    public $id;
    public $resource;
    public $base;
    public function __construct()
    {
        if (!mtoShell::hasLibevent())
        {
            throw new mtoException('You need to install PECL extension "Libevent" to use this class', 2);
        }

        $this->id = ++self::$counter;
    }

    public function __destruct()
    {
        $this->resource && $this->free();
    }

    public function setBase($event_base)
    {
        $this->base = $event_base;
        $event_base->events[$this->id] = $this;
        return $this;
    }

    public function free()
    {
        if ($this->base)
        {
            unset($this->base->events[$this->id]);
            $this->base = null;
        }
    }

    protected function checkResourse()
    {
        if (!$this->resource)
        {
            throw new mtoException('Can\'t use event resource. It\'s already freed.', 2);
        }
    }

}
