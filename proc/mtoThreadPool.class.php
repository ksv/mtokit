<?php

mtoClass :: import("mtokit/thread/mtoShell.class.php");

class mtoThreadPool extends mtoShell
{

    protected static $allPoolsCount = 0;
    protected $maxThreads = 4;
    protected $id;
    protected $pid;
    protected $poolName;
    protected $tName;
    protected $pName;
    protected $waitNumber;
    public $threads = array();
    public $waiting = array();
    public $working = array();
    public $initializing = array();
    public $failed = array();
    public $results = array();
    public $threadsCount = 0;
    public $debug = false;

    public function __construct($threadName, $maxThreads = null, $pName = null, $debug = false, $name = 'base')
    {
        $debug && $this->debug = true;

        $this->id = ++self::$allPoolsCount;
        $this->pid = posix_getpid();
        $this->poolName = $name;
        $this->tName = $threadName;

        if (!mtoThread::useForks())
        {
            $this->maxThreads = 1;
        }
        if (null !== $maxThreads)
        {
            $this->setMaxThreads($maxThreads);
        }

        if (null !== $pName)
        {
            $this->pName = $pName;
        }

        $this->debug("Pool of '$threadName' threads created.");

        $this->createAllThreads();
    }

    public function __destruct()
    {
        $this->debug('Destructor');
        $this->cleanup();
    }

    public function cleanup()
    {
        $this->debug('Cleanup');
        foreach ($this->threads as $thread)
        {
            $thread->cleanup();
        }
    }

    protected function createAllThreads()
    {
        if (($count = &$this->threadsCount) < ($tMax = $this->maxThreads))
        {
            do
            {
                /** @var $thread CThread */
                $thread = $this->tName;
                $thread = new $thread($this->debug, $this->pName, $this);
                $id = $thread->getId();
                $this->threads[$id] = $thread;
                $count++;
                $this->debug("Thread #$id created");
            }
            while ($count < $tMax);
        }
    }

    public function run()
    {
        $this->createAllThreads();
        if ($this->hasWaiting())
        {
            $threadId = reset($this->waiting);
            $thread = $this->threads[$threadId];
            $args = func_get_args();
            if (($count = count($args)) === 0)
            {
                $thread->run();
            }
            else if ($count === 1)
            {
                $thread->run($args[0]);
            }
            else if ($count === 2)
            {
                $thread->run($args[0], $args[1]);
            }
            else if ($count === 3)
            {
                $thread->run($args[0], $args[1], $args[2]);
            }
            else
            {
                call_user_func_array(array($thread, 'run'), $args);
            }
            $this->waitNumber--;
            $this->debug("Thread #$threadId started");
            return $threadId;
        }
        return false;
    }

    public function wait(&$failed = null)
    {
        $this->waitNumber = null;
        if ($this->results || $this->failed)
        {
            return $this->getResults($failed);
        }
        if (($w = $this->working) || $this->initializing)
        {
            if ($this->initializing)
            {
                $w += $this->initializing;
            }
            $this->debug && $this->debug('Waiting for threads: ' . join(', ', $w));
            mtoThread::waitThreads($w);
        }
        else
        {
            throw new mtoException('Nothing to wait in pool');
        }
        return $this->getResults($failed);
    }

    public function hasWaiting()
    {
        if ($this->waitNumber === null && $this->waiting)
        {
            $this->waitNumber = count($this->waiting);
            return true;
        }
        else
        {
            return $this->waitNumber > 0;
        }
    }

    protected function getResults(&$failed = null)
    {
        if ($res = $this->results)
        {
            $this->results = array();
        }
        else
        {
            $res = false;
        }
        $failed = $this->failed;
        $this->failed = array();
        return $res;
    }

    protected function getState()
    {
        $state = array();
        foreach ($this->threads as $threadId => $thread)
        {
            $state[$threadId] = $thread->getStateName();
        }
        return $state;
    }

    public function setMaxThreads($value)
    {
        if ($value < $this->threadsCount)
        {
            $value = $this->threadsCount;
        }
        else if (!mtoThread::useForks() || $value < 1)
        {
            $value = 1;
        }
        $this->maxThreads = (int) $value;
    }

    protected function debug($message)
    {
        if (!$this->debug)
        {
            return;
        }

        $time = mtoShell::getLogTime();
        $message = "{$time} [debug] [P{$this->id}.{$this->poolName}] #{$this->pid}: {$message}";

        echo $message;
        @ob_flush();
        @flush();
    }

}
