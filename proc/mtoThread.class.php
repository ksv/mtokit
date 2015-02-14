<?php

mtoClass :: import("mtokit/thread/mtoShell.class.php");
mtoClass :: import("mtokit/thread/mtoSocket.class.php");

abstract class mtoThread extends mtoShell
{
    const EOP = "\3\0\4";

    // States
    const STATE_TERM = 0x1; // Reserved
    const STATE_INIT = 0x2;
    const STATE_WAIT = 0x4;
    const STATE_WORK = 0x8;

    // Packets
    const P_DATA = 0x01;
    const P_STATE = 0x02;
    const P_JOB = 0x04;
    const P_EVENT = 0x08;
    const P_SERIAL = 0x10;

    // Timer names
    const IPC_IGBINARY = 1; // Igbinary			(8th, 6625 jps)
    const IPC_SERIALIZE = 2; // Serialization		(8th, 6501 jps)
    const IPC_SYSV_QUEUE = 3; // SysV Memory queue	(8th, 6194 jps)
    const IPC_SYSV_SHM = 4; // SysV Shared memory	(8th, 6008 jps)
    const IPC_SHMOP = 5; // Shared memory		(8th, 6052 jps)
    // Timer names
    const TIMER_BASE = 'thread:base:';
    const TIMER_WAIT = 'thread:wait:';

    // Debug prefixes
    const D_INIT = 'INIT: ';
    const D_WARN = 'WARN: ';
    const D_INFO = 'INFO: ';
    const D_IPC = 'IPC: ';   // IPC
    const D_CLEAN = 'CLEAN: '; // Cleanup

    public static $ipcDataMode = self::IPC_SERIALIZE;
    public static $useForks = false;
    private static $threadsCount = 0;
    private static $threads = array();
    private static $threadsByClasses = array();
    private static $threadsByPids = array();
    private static $shms = array();
    private static $queues = array();
    private static $pipesMaster;
    private static $waitingThreads = array();
    private $events = array();
    private static $masterPipeEvent;
    private static $eventsSignals = array();
    private $eventsTimers = array();
    private $listeners = array();
    protected $pool;
    protected $id;
    protected $pid;
    protected $parent_pid;
    protected $child_pid;
    protected $isForked = false;
    protected $isChild = false;
    protected $params = array();
    protected $result;
    protected $success = false;
    private $processName = false;
    protected $state;
    private $pipesWorker;
    private $waiting = false;
    private $eventLocking = false;
    protected $file;
    protected $multitask = true;
    protected $listenMasterSignals = true;
    protected $prefork = true;
    protected $intervalWorker = 15;
    protected $timeoutInit = 3;
    protected $timeoutWork = 5;
    protected $timeoutMaxWait = -1;
    protected $pipeReadSize = 65536;
    protected static $pipeMasterReadSize = 65536;
    private $cleaning = false;
    public $debug = false;

    public function __construct($debug = false, $pName = null, $pool = null)
    {
        $this->id = $id = ++self::$threadsCount;
        $class = get_class($this);

        self::$threadsByClasses[$class][$id] = $this;
        self::$threads[$id] = $this;

        $debug && $this->debug = true;
        $pool && $this->pool = $pool;
        $pName && $this->processName = $pName;

        $pid = posix_getpid();
        $this->pid = $pid;
        $this->parent_pid = $pid;
        $this->child_pid = $pid;

        $this->setState(self::STATE_INIT);

        $forks = self::useForks();

        if ($debug = $this->debug)
        {
            $message = 'Thread of type "' . get_class($this) . '" created.';
            $this->debug(self::D_INIT . $message);
            if (!$forks)
            {
                $debug && $this->debug(self::D_WARN . 'Sync mode (you need Forks and LibEvent support and CLI sapi to use threads asynchronously)');
            }
        }

        // Forks preparing
        if ($forks)
        {
            $mode = self::ipcDataMode();

            // IPC
            if (self::IPC_SHMOP === $mode || self::IPC_SYSV_SHM === $mode || self::IPC_SYSV_QUEUE === $mode)
            {
                // IPC File check
                if (null === $this->file)
                {
                    $r = new ReflectionClass($this);
                    $this->file = $r->getFileName();
                    $debug && $this->debug(self::D_INIT . "IPC file: {$this->file}");
                }
                // IPC Shared memory
                if (self::IPC_SHMOP === $mode)
                {
                    mtoClass :: import("mtokit/thread/mtoIpcShmop.class.php");
                    $shm = mtoIpcShmop::instance(
                                    $this->file, chr($id),
                                    mtoIpcShmop::MODE_CREATE_READ_WRITE,
                                    max(self::$pipeMasterReadSize, $this->pipeReadSize)
                    );
                    self::$shms[$id] = $shm;
                    $debug && $this->debug(self::D_INIT . 'IPC Shared memory initialized');
                    $this->eventLocking = true;
                }
                // IPC SysV Shared memory
                else if (self::IPC_SYSV_SHM === $mode)
                {
                    mtoClass :: import("mtokit/thread/mtoIpcSharedMemory.class.php");
                    $shm = mtoIpcSharedMemory::instance(
                                    $this->file, chr($id),
                                    max(self::$pipeMasterReadSize, $this->pipeReadSize)
                    );
                    self::$shms[$id] = $shm;
                    $debug && $this->debug(self::D_INIT . 'SysV IPC Shared memory initialized');
                    $this->eventLocking = true;
                }
                // IPC Memory queue
                else
                {
                    mtoClass :: import("mtokit/thread/mtoIpcQueue.class.php");
                    $queue = mtoIpcQueue::instance($this->file, chr($id));
                    $queue->blocking = false;
                    $queue->blockingTimeout = 0;
                    $queue->blockingWait = 0;
                    $queue->maxMsgSize = max(self::$pipeMasterReadSize, $this->pipeReadSize);
                    self::$queues[$id] = $queue;
                    $debug && $this->debug(self::D_INIT . 'IPC Memory queue initialized');
                }
            }

            // Master pipes
            if (null === self::$pipesMaster)
            {
                self::$pipesMaster = mtoSocket::pair();
                $debug && $this->debug(self::D_INIT . 'Master pipes initialized');
            }

            // Libevent check
            if (!self::hasLibevent())
            {
                throw new mtoException('Threads in fork mode currently supported only with Libevent');
            }

            // Shared master event base
            if (null === self::$eventBase)
            {
                self::$eventBase = new mtoLibEventBase();
                $debug && $this->debug(self::D_INIT . 'Master event base initialized');
            }

            // Master signals
            if (!self::$eventsSignals)
            {
                if ($this->listenMasterSignals)
                {
                    $this->registerEventSignals();
                }
                else
                {
                    $signo = SIGCHLD;
                    $e = new mtoLibEvent();
                    $e->setSignal($signo, array($class, '_mEvCbSignal'))
                            ->setBase(self::$eventBase)
                            ->add();
                    self::$eventsSignals[$signo] = $e;
                    $debug && $this->debug(
                                    self::D_INIT . 'Master SIGCHLD event signal handler initialized'
                    );
                }
            }

            // Master pipe event
            if (null === self::$masterPipeEvent)
            {
                $ev = new mtoLibEventBuffer(
                                self::$pipesMaster[0],
                                array($class, '_mEvCbRead'),
                                null,
                                function()
                                {

                                }
                );
                $ev->setBase(self::$eventBase)->setPriority()->enable(EV_READ);
                self::$masterPipeEvent = $ev;
                $debug && $this->debug(self::D_INIT . 'Master pipe event initialized');
            }

            // Master timer
            $timer_name = self::TIMER_BASE . $this->id;
            self::$eventBase->timerAdd(
                    $timer_name,
                    0,
                    array($this, '_mEvCbTimer'),
                    null,
                    false
            );
            $this->eventsTimers[] = $timer_name;
            $debug && $this->debug(self::D_INIT . "Master timer ($timer_name) added");
        }

        // Hook
        $this->onLoad();

        // Preforking
        if ($forks && $this->prefork)
        {
            $debug && $this->debug(self::D_INFO . 'Preforking');
            if ($this->forkThread())
            {
                // Parent
                if (($interval = $this->timeoutInit) > 0)
                {
                    $timer_name = self::TIMER_BASE . $this->id;
                    self::$eventBase->timerStart(
                            $timer_name,
                            $interval,
                            self::STATE_INIT
                    );
                    $debug && $this->debug(
                                    self::D_INFO . "Master timer ($timer_name) started for INIT ($interval sec)"
                    );
                }
                $this->wait();
            }
            else
            {
                // Child
                $this->setState(self::STATE_WAIT);
                $this->evWorkerLoop();
                $debug && $this->debug(self::D_INFO . 'Preforking: end of loop, exiting');
                $this->shutdown();
            }
        }
        else
        {
            $this->setState(self::STATE_WAIT);
        }
    }

    
    public static function ipcDataMode()
    {
        if (function_exists('igbinary_serialize'))
        {
            return self::IPC_IGBINARY;
        }
        else
        {
            return self :: IPC_SERIALIZE;
        }
    }
    
    public function __destruct()
    {
        $this->debug(self::D_INFO . 'Destructor');
        $this->cleanup();
    }

    public function cleanup()
    {
        if ($this->cleaning)
        {
            return;
        }
        $this->cleaning = true;
        $this->state = self::STATE_TERM;

        ($debug = $this->debug) && $this->debug(self::D_CLEAN . 'Cleanup');

        $id = $this->id;
        $class = get_class($this);
        $isMaster = !$this->isChild;

        // Threads pool
        if ($pool = &$this->pool)
        {
            unset($pool->threads[$id]);
            $pool->threadsCount--;
            $this->pool = null;
        }

        // Child process
        $base = &self::$eventBase;
        if ($isMaster && $this->isForked)
        {
            $this->stopWorker(true);
            $debug && $this->debug(self::D_CLEAN . 'Worker process terminated');

            // Check for non-triggered events
            if ($base && $base->resource)
            {
                $base->loop(EVLOOP_NONBLOCK);
            }
            else
            {
                $base = null;
            }
        }

        // Threads storage
        unset(self::$threads[$id]);
        unset(self::$threadsByClasses[$class][$id]);
        if (empty(self::$threadsByClasses[$class]))
        {
            unset(self::$threadsByClasses[$class]);
        }

        // Events
        foreach ($this->events as $ev)
        {
            $ev->free();
        }
        if ($base && $base->resource)
        {
            foreach ($this->eventsTimers as $t)
            {
                self::$eventBase->timerDelete($t);
            }
        }
        if (!$isMaster || !self::$threads)
        {
            foreach (self::$eventsSignals as $ev)
            {
                $ev->free();
            }
            self::$eventsSignals = array();
        }
        $this->events = array();
        $this->eventsTimers = array();
        $debug && $this->debug(self::D_CLEAN . 'All events freed');

        // Worker pipes
        if (null !== $this->pipesWorker)
        {
            mtoSocket::close($this->pipesWorker[0]);
            if ($isMaster)
            {
                // It's already closed after forking
                mtoSocket::close($this->pipesWorker[1]);
            }
            $debug && $this->debug(self::D_CLEAN . 'Worker pipes destructed');
            $this->pipesWorker = null;
        }

        // Mster cleanup
        if ($isMaster)
        {
            // Memory queue
            if (isset(self::$queues[$id]))
            {
                self::$queues[$id]->destroy();
                unset(self::$queues[$id]);
                $debug && $this->debug(self::D_CLEAN . 'Memory queue destructed');
            }

            // Shared memory
            if (isset(self::$shms[$id]))
            {
                self::$shms[$id]->destroy();
                unset(self::$shms[$id]);
                $debug && $this->debug(self::D_CLEAN . 'IPC Shared memory destructed');
            }

            // Last master thread cleanup
            if (!self::$threads)
            {
                // Master pipe event
                if (null !== self::$masterPipeEvent)
                {
                    self::$masterPipeEvent->free();
                    self::$masterPipeEvent = null;
                    $debug && $this->debug(self::D_CLEAN . 'Master pipe event freed');
                }

                // Master pipes
                if (null !== self::$pipesMaster)
                {
                    mtoSocket::close(self::$pipesMaster[0]);
                    mtoSocket::close(self::$pipesMaster[1]);
                    $debug && $this->debug(self::D_CLEAN . 'Common master pipes destructed');
                    self::$pipesMaster = null;
                }
            }
        }

        // Child cleanup
        else
        {
            // Memory queue
            unset(self::$queues[$id]);

            // Shared memory
            unset(self::$shms[$id]);

            // Master pipes
            if (null !== self::$pipesMaster)
            {
                // Read master pipe is already closed after forking
                mtoSocket::close(self::$pipesMaster[1]);
                $debug && $this->debug(self::D_CLEAN . 'Master pipes destructed');
                self::$pipesMaster = null;
            }
        }
    }

    protected function onLoad()
    {

    }

    private function forkThread()
    {
        // Checks
        if (!self::useForks())
        {
            throw new mtoException('Can\'t fork thread. Forks are not supported.');
        }
        else if ($this->isForked)
        {
            throw new mtoException('Can\'t fork thread. It is already forked.');
        }

        // Worker pipes
        $debug = $this->debug;
        if (null === $this->pipesWorker)
        {
            $this->pipesWorker = CSocket::pair();
            $debug && $this->debug(self::D_INIT . 'Worker pipes initialized');
        }

        // Forking
        $debug && $this->debug(self::D_INIT . 'Forking');
        $this->isForked = true;
        $pid = self::fork();

        // In parent
        if ($pid)
        {
            self::$threadsByPids[$pid] = $this->id;
            $this->child_pid = $pid;
            $debug && $this->debug(self::D_INIT . "Forked: parent ($this->pid)");
            return true;
        }

        // In child
        $this->isChild = true;
        $pid = posix_getpid();
        $this->pid = $pid;
        $this->child_pid = $pid;
        $debug && $this->debug(self::D_INIT . "Forked: child ($pid)");
        // Closing reading master pipe and writing worker pipe
        mtoSocket::close(self::$pipesMaster[0]);
        mtoSocket::close($this->pipesWorker[1]);
        // Cleanup parent events
        $this->events = array();
        $this->eventsTimers = array();
        self::$eventsSignals = array();
        // Event base
        if (self::$hasLibevent)
        {
            self::$eventBase = new mtoLibEventBase();
            $debug && $this->debug(self::D_INIT . 'Worker event base initialized');
        }
        // Process name
        if ($this->processName)
        {
            $name = $this->processName . ' (aza-php): worker';
            self::setProcessTitle($name);
            $debug && $this->debug(self::D_INIT . "Child process name changed to: $name");
        }

        return false;
    }

    public function run()
    {
        if (!$this->getIsWaiting())
        {
            throw new mtoException("Can't run thread. It is not in waiting state.");
        }

        $this->debug(self::D_INFO . 'Job start');
        $this->setState(self::STATE_WORK);
        $this->result = null;
        $this->success = false;

        $args = func_get_args();

        // Emulating thread with fork
        if (self::useForks())
        {
            // Thread is alive
            if ($this->getIsAlive())
            {
                $this->debug(self::D_INFO . "Child is already running ($this->child_pid)");
                $this->sendPacketToChild(self::P_JOB, $args ? : null);
                $this->startWorkTimeout();
            }
            // Forking
            else
            {
                if ($this->forkThread())
                {
                    // Parent
                    $this->startWorkTimeout();
                }
                else
                {
                    // Child
                    $this->setParams($args);
                    $res = $this->process();
                    $this->setResult($res);
                    if ($this->multitask)
                    {
                        $this->evWorkerLoop();
                    }
                    $this->debug(self::D_INFO . 'Simple end of work, exiting');
                    return $this->shutdown();
                }
            }
        }
        // Forkless compatibility
        else
        {
            $this->setParams($args);
            $res = $this->process();
            $this->setResult($res);
            $this->debug(self::D_INFO . 'Sync job ended');
        }

        return $this;
    }

    abstract protected function process();

    public function wait()
    {
        if (self::useForks() && !$this->getIsWaiting())
        {
            $this->debug(self::D_INFO . 'Loop (master waiting) start');
            $this->waiting = true;
            self::evMasterLoop();
            if (!$this->getIsWaiting())
            {
                throw new mtoException('Could not wait for the thread');
            }
        }
    }

    public static function waitThreads($threadIds)
    {
        if (self::useForks() && $threadIds)
        {
            self::stDebug(self::D_INFO . 'Loop (master theads waiting) start');
            $threadIds = (array) $threadIds;
            $threadIds = array_combine($threadIds, $threadIds);
            self::$waitingThreads = $threadIds;
            self::evMasterLoop();
            self::$waitingThreads = array();
        }
    }

    private static function evMasterLoop()
    {
        ($debug = self::stGetDebug()) && self::stDebug(self::D_INFO . 'Loop (master) start');
        self::$eventBase->loop();
        $debug && self::stDebug(self::D_INFO . 'Loop (master) end');
    }

    private function startWorkTimeout()
    {
        if (($interval = $this->timeoutWork) > 0)
        {
            $timer_name = self::TIMER_BASE . $this->id;
            self::$eventBase->timerStart($timer_name, $interval, self::STATE_WORK);
            $this->debug(self::D_INFO . "Master timer ($timer_name) started for WORK ($interval sec)");
        }
    }

    public function bind($event, $listener, $arg = null)
    {
        // Bind is allowed only in parent
        if (!$this->isChild)
        {
            if (!isset($this->listeners[$event]))
            {
                $this->listeners[$event] = array();
            }
            $this->listeners[$event][] = array($listener, $arg);
            $this->debug(self::D_INFO . "New listener binded on event [$event]");
        }
    }

    public function trigger($event, $data = null)
    {
        $this->debug(self::D_INFO . "Triggering event [$event]");

        // Child
        if ($this->isChild)
        {
            $this->sendPacketToParent(self::P_EVENT, $event, $data);
            if (null !== $data && $this->eventLocking)
            {
                $this->debug(self::D_INFO . "Locking thread - waiting for event read confirmation");
                $this->waiting = true;
            }
        }
        // Parent
        else if (!empty($this->listeners[$event]))
        {
            /** @var $listener callback */
            foreach ($this->listeners[$event] as $l)
            {
                list($listener, $arg) = $l;
                if (!is_array($listener))
                {
                    $listener($event, $data, $arg);
                }
                else
                {
                    call_user_func($listener, $event, $data, $arg);
                }
            }
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSuccess()
    {
        return $this->success;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getStateName()
    {
        $state = $this->state;
        if (self::STATE_WAIT === $state)
        {
            return 'wait';
        }
        else if (self::STATE_WORK === $state)
        {
            return 'work';
        }
        else if (self::STATE_INIT === $state)
        {
            return 'init';
        }
        else if (self::STATE_TERM === $state)
        {
            return 'term';
        }
        else
        {
            return 'unknown';
        }
    }

    protected function getParam($index, $default = null)
    {
        return isset($this->params[$index]) ? $this->params[$index] : $default;
    }

    private function getIsWaiting()
    {
        return $this->state === self::STATE_WAIT;
    }

    private function getIsAlive()
    {
        return !self::useForks() || $this->isForked && 0 === pcntl_waitpid($this->child_pid, $s, WNOHANG);
    }

    private function setParams($args)
    {
        $msg = $this->isChild ? 'Async processing' : 'Processing';
        if ($args && is_array($args))
        {
            $msg .= ' with args';
            $this->params = $args;
        }
        else
        {
            $this->params = array();
        }
        $this->debug(self::D_INFO . $msg);
    }

    private function setResult($res = null)
    {
        $this->debug(self::D_INFO . 'Setting result');

        // Send result packet to parent
        if ($this->isChild)
        {
            $this->sendPacketToParent(self::P_JOB, null, $res);
        }

        // Change result
        else
        {
            if ($pool = $this->pool)
            {
                $pool->results[$this->id] = $res;
            }
            $this->result = $res;
            $this->success = true;
            $this->setState(self::STATE_WAIT);
        }
    }

    private function setState($state)
    {
        $state = (int) $state;

        if ($debug = $this->debug)
        {
            $s = 'Changing state to: "';
            if ($state & self::STATE_INIT)
            {
                $s .= 'INIT';
            }
            else if ($state & self::STATE_TERM)
            {
                $s .= 'TERM';
            }
            else if ($state & self::STATE_WAIT)
            {
                $s .= 'WAIT';
            }
            else if ($state & self::STATE_WORK)
            {
                $s .= 'WORK';
            }
            $s .= "\" ($state)";
            $this->debug(self::D_INFO . $s);
        }

        // Send state packet to parent
        if ($this->isChild)
        {
            $this->sendPacketToParent(self::P_STATE, $state);
        }

        // Change state
        else
        {
            $this->state = $state;
            $wait = (bool) ($state & self::STATE_WAIT);
            $threadId = $this->id;
            if ($pool = $this->pool)
            {
                if ($wait)
                {
                    unset(
                            $pool->working[$threadId],
                            $pool->initializing[$threadId]
                    );
                    $pool->waiting[$threadId] = $threadId;
                    if (!$this->success && isset(self::$waitingThreads[$threadId]))
                    {
                        $pool->failed[$threadId] = $threadId;
                    }
                }
                else
                {
                    unset($pool->waiting[$threadId]);
                    if ($state & self::STATE_WORK)
                    {
                        $pool->working[$threadId] = $threadId;
                        unset($pool->initializing[$threadId]);
                    }
                    else if ($state & self::STATE_INIT)
                    {
                        $pool->initializing[$threadId] = $threadId;
                        unset($pool->working[$threadId]);
                    }
                }
            }
            if ($wait && self::$useForks)
            {
                $base = self::$eventBase;
                $timer_name = self::TIMER_BASE . $threadId;
                $base->timerStop($timer_name);
                $debug && $this->debug(self::D_INFO . "Master timer ($timer_name) stopped");
                // Waiting thread
                if ($this->waiting)
                {
                    $this->waiting = false;
                    $debug && $this->debug(self::D_INFO . 'Loop (master waiting) end');
                    $base->loopExit();
                }
                // Waiting threads
                else if (isset(self::$waitingThreads[$threadId]))
                {
                    $debug && $this->debug(self::D_INFO . 'Loop (master theads waiting) end');
                    $base->loopExit();
                }
            }
        }
    }

    private function evWorkerLoop()
    {
        ($debug = $this->debug) && $this->debug(self::D_INIT . "Preparing worker loop");

        if (!$this->isChild)
        {
            throw new mtoException('Can\'t start child loop in parent');
        }

        $this->registerEventSignals();

        $base = self::$eventBase;

        $ev = new mtoLibEventBuffer(
                        $this->pipesWorker[0],
                        array($this, '_wEvCbRead'),
                        null,
                        function()
                        {
                            
                        }
        );
        $ev->setBase($base)->setPriority()->enable(EV_READ);
        $this->events[] = $ev;
        $debug && $this->debug(self::D_INIT . 'Worker pipe event initialized');

        $timer = self::TIMER_BASE;
        $timerCb = array($this, '_wEvCbTimer');
        ($timeout = $this->intervalWorker) > 0 || $timeout = 15;
        $base->timerAdd($timer, $timeout, $timerCb);
        $this->eventsTimers[] = $timer;
        $debug && $this->debug(self::D_INIT . "Worker timer ($timer) event initialized and set to $timeout");

        if (($timeout = $this->timeoutMaxWait) > 0)
        {
            $timer = self::TIMER_WAIT;
            $base->timerAdd($timer, $timeout, $timerCb);
            $this->eventsTimers[] = $timer;
            $debug && $this->debug(self::D_INIT . "Worker wait timer ($timer) event initialized and set to $timeout");
        }

        $debug && $this->debug(self::D_INFO . 'Loop (worker) start');
        $base->loop();
        $debug && $this->debug(self::D_INFO . 'Loop (worker) end');
    }

    public function _wEvCbRead($buf, $args)
    {
        ($debug = $this->debug) && $this->debug(self::D_INFO . 'Worker pipe event');

        $packets = self::readPackets($args[0], $this->pipeReadSize);

        // Restart waiting timeout
        if (self::$eventBase->timerExists($timer = self::TIMER_WAIT))
        {
            self::$eventBase->timerStart($timer);
        }

        $debug && $this->debug(self::D_IPC . ' => Packets received: [' . count($packets) . ']');

        foreach ($packets as $p)
        {
            list($packet, $data) = explode(':', $p, 2);

            $debug && $this->debug(self::D_IPC . " => Packet: [$packet]");

            $packet = (int) $packet;
            $threadId = $this->id;

            // Job packet
            if ($packet & self::P_JOB)
            {
                if ($packet & self::P_DATA)
                {
                    self::peekPacketData($data, $threadId, $packet);
                    $debug && $this->debug(self::D_IPC . ' => Packet: job with arguments');
                }
                else
                {
                    $debug && $this->debug(self::D_IPC . ' => Packet: job');
                    $data = array();
                }
                $this->setParams($data);
                $res = $this->process();
                $this->setResult($res);
            }

            // Event read confirmation
            else if ($packet & self::P_EVENT)
            {
                $debug && $this->debug(self::D_IPC . ' => Packet: event read confirmation. Unlocking thread');
                $this->waiting = false;
            }

            // Unknown packet
            else
            {
                self::$eventBase->loopBreak();
                throw new mtoException("Unknown packet [$packet]");
            }
        }
    }

    public function _wEvCbTimer($base, $name)
    {
        $die = false;

        // Worker wait
        if ($name === self::TIMER_WAIT)
        {
            $this->debug(self::D_WARN . 'Timeout (worker waiting) exceeded, exiting');
            $die = true;
        }
        // Worker check
        elseif (!self::getProcessIsAlive($this->parent_pid))
        {
            $this->debug(self::D_WARN . 'Parent is dead, exiting');
            $die = true;
        }

        if ($die)
        {
            self::$eventBase->loopBreak();
            $this->shutdown();
        }
    }

    public static function _mEvCbRead($buf, $args)
    {
        ($debug = self::stGetDebug()) && self::stDebug(self::D_INFO . 'Master pipe event');

        $packets = self::readPackets($args[0], self::$pipeMasterReadSize);

        $debug && self::stDebug(self::D_IPC . ' <= Packets received: [' . count($packets) . ']');

        foreach ($packets as $p)
        {
            list($packet, $threadId, $value, $data) = explode(':', $p, 4);

            $debug && self::stDebug(self::D_IPC . " <= Packet: [$packet]");

            if (!isset(self::$threads[$threadId]))
            {
                self::$eventBase->loopBreak();
                throw new mtoException("Packet [$packet:$value] for unknown thread #$threadId");
            }

            $thread = self::$threads[$threadId];
            $packet = (int) $packet;

            // Packet data
            if (self::P_DATA & $packet)
            {
                self::peekPacketData($data, $threadId, $packet);
                $debug && self::stDebug(self::D_IPC . ' <= Packet: data received');
            }
            else
            {
                $data = null;
            }

            // State packet
            if (self::P_STATE & $packet)
            {
                $debug && self::stDebug(self::D_IPC . ' <= Packet: state');
                $thread->setState($value);
            }

            // Event packet
            else if (self::P_EVENT & $packet)
            {
                $debug && self::stDebug(self::D_IPC . ' <= Packet: event');
                if ($thread->eventLocking)
                {
                    $debug && self::stDebug(self::D_IPC . " => Sending event read confirmation");
                    $thread->sendPacketToChild(self::P_EVENT);
                }
                $thread->trigger($value, $data);
            }

            // Job packet
            else if (self::P_JOB & $packet)
            {
                $debug && self::stDebug(self::D_IPC . ' <= Packet: job ended');
                $thread->setResult($data);
            }

            // Unknown packet
            else
            {
                self::$eventBase->loopBreak();
                throw new mtoException("Unknown packet [$packet]");
            }
        }
    }

    public function _mEvCbTimer($base, $name, $iteration, $arg)
    {
        $this->debug(self::D_WARN . "Master timeout exceeded ($name - $arg)");

        $killed = $this->stopWorker();

        $arg = (int) $arg;
        if (self::STATE_WORK & $arg)
        {
            if ($killed)
            {
                throw new mtoException("Exceeded timeout: thread work ($this->timeoutWork sec.)");
            }
        }
        else if (self::STATE_INIT & $arg)
        {
            throw new mtoException("Exceeded timeout: thread initialization ($this->timeoutInit sec.)");
        }
        else
        {
            throw new mtoException("Unknown timeout ($name ($iteration) - $arg)");
        }
    }

    private function sendPacketToParent($packet, $value = '', $data = null)
    {
        $debug = $this->debug;
        if ($this->waiting)
        {
            if ($debug)
            {
                $this->debug(self::D_INFO . "Thread is locked waiting for read confirmation");
                $this->debug(self::D_INFO . 'Loop (worker) start once');
            }
            self::$eventBase->loop(EVLOOP_ONCE);
            $debug && $this->debug(self::D_INFO . 'Loop (worker) end once');
            if ($this->waiting)
            {
                self::$eventBase->loopBreak();
                throw new mtoException(
                        "Can't send packet to parent. Child is waiting for event read confirmation."
                );
            }
        }
        $postfix = '';
        if ($data !== null)
        {
            $postfix = ' (with data)';
            $this->preparePacketData($packet, $data);
        }
        $debug && $this->debug(self::D_IPC . " <= Sending packet$postfix to parent: [$packet]");
        if (($value = (string) $value) !== '' && strpos($value, ':') !== false)
        {
            self::$eventBase->loopBreak();
            throw new mtoException("IPC Packet value can't contain the ':' character", 1);
        }
        $packet = "$packet:{$this->id}:$value:$data";
        mtoSocket::write(self::$pipesMaster[1], $packet . self::EOP);
    }

    private function sendPacketToChild($packet, $data = null)
    {
        $postfix = '';
        if ($data !== null)
        {
            $postfix = ' (with data)';
            $this->preparePacketData($packet, $data);
        }
        $this->debug(self::D_IPC . " => Sending packet$postfix to child: [$packet]");
        $packet = "$packet:$data";
        mtoSocket::write($this->pipesWorker[1], $packet . self::EOP);
    }

    private static function readPackets($e, $pipeReadSize)
    {
        $eop = self::EOP;
        $eop_len = strlen($eop);
        $packets = '';
        do
        {
            $packets .= $e->read($pipeReadSize);
            $end = substr($packets, -$eop_len);
        }
        while ($end !== $eop);
        $packets = substr($packets, 0, -$eop_len);
        $packets = explode($eop, $packets);
        return $packets;
    }

    private function preparePacketData(&$packet, &$data)
    {
        $packet |= self::P_DATA;
        $mode = self::$ipcDataMode;

        // Shared memory
        if (self::IPC_SHMOP === $mode)
        {
            $data = self::$shms[$this->id]->write($data);
        }
        // SysV Shared memory
        elseif (self::IPC_SYSV_SHM === $mode)
        {
            $id = $this->id;
            self::$shms[$id]->set($id, $data);
            $data = '';
        }
        // Memory queue
        else if (self::IPC_SYSV_QUEUE === $mode)
        {
            self::$queues[$this->id]->put($data);
            $data = '';
        }
        // Serialization
        else
        {
            if (is_scalar($data))
            {
                if (is_bool($data))
                {
                    $data = (int) $data;
                }
                $data = (string) $data;
            }
            else
            {
                $packet |= self::P_SERIAL;
                // Igbinary serialize
                if (self::IPC_IGBINARY === $mode)
                {
                    $data = igbinary_serialize($data);
                }
                // PHP serialize
                else
                {
                    $data = serialize($data);
                }
            }
        }
    }

    private static function peekPacketData(&$data, $threadId, $packet)
    {
        $mode = self::$ipcDataMode;
        // Shared memory
        if (self::IPC_SHMOP === $mode)
        {
            $data = self::$shms[$threadId]->read(0, $data);
        }
        // Shared memory
        else if (self::IPC_SYSV_SHM === $mode)
        {
            $data = self::$shms[$threadId]->getOnce($threadId, $data);
        }
        // Memory queue
        else if (self::IPC_SYSV_QUEUE === $mode)
        {
            $queue = self::$queues[$threadId];
            $data = $queue->peek();
        }
        // Serialization
        elseif (self::P_SERIAL & $packet)
        {
            // Igbinary serialize
            if (self::IPC_IGBINARY === $mode)
            {
                $data = igbinary_unserialize($data);
            }
            // PHP serialize
            else
            {
                $data = unserialize($data);
            }
        }
    }

    private function sendSignalToParent($signo = SIGUSR1)
    {
        if ($this->debug)
        {
            $name = self::signalName($signo);
            $this->debug(self::D_IPC . " <= Sending signal to the parent - $name ($signo) ($this->pid => $this->parent_pid)");
        }
        posix_kill($this->parent_pid, $signo);
    }

    private function sendSignalToChild($signo = SIGUSR1)
    {
        if ($this->debug)
        {
            $name = self::signalName($signo);
            $this->debug(self::D_IPC . " => Sending signal to the child - $name ($signo) ($this->pid => $this->child_pid)");
        }
        posix_kill($this->child_pid, $signo);
    }

    private function registerEventSignals()
    {
        if (self::$eventsSignals)
        {
            throw new mtoException('Signal events are already registered');
        }
        $base = self::$eventBase;
        $i = 0;
        $cb = $this->isChild ? array($this, '_evCbSignal') : array(get_class($this), '_mEvCbSignal');
        foreach (self::$signals as $signo => $name)
        {
            if ($signo === SIGKILL || $signo === SIGSTOP)
            {
                continue;
            }
            $ev = new mtoLibEvent();
            $ev->setSignal($signo, $cb)
                    ->setBase($base)
                    ->add();
            self::$eventsSignals[$signo] = $ev;
            $i++;
        }
        $this->debug(self::D_INIT . "Signals event handlers registered ($i)");
    }

    public function _evCbSignal($fd, $events, $arg)
    {
        $this->signalHandler($arg[2]);
    }

    private function signalHandler($signo)
    {
        $name = self::signalName($signo, $found);
        if ($debug = $this->debug)
        {
            $prefix = $this->isChild ? '=>' : '<=';
            $this->debug(self::D_IPC . " {$prefix} Caught $name ($signo) signal");
        }
        if ($found && method_exists($this, $name))
        {
            $this->$name();
        }
        else if (method_exists($this, $name = 'sigUnknown'))
        {
            $this->$name($signo);
        }
        else
        {
            $debug && $this->debug(self::D_INFO . 'Unhandled signal, exiting');
            self::$eventBase->loopBreak();
            $this->shutdown();
        }
    }

    protected function sigInfo()
    {
        
    }

    protected function sigWinch()
    {

    }

    public static function _mEvCbSignal($fd, $events, $arg)
    {
        self::mSignalHandler($arg[2]);
    }

    private static function mSignalHandler($signo)
    {
        $name = self::signalName($signo, $found);
        if ($debug = self::stGetDebug($thread))
        {
            $prefix = $thread->isChild ? '=>' : '<=';
            self::stDebug(self::D_IPC . " {$prefix} Caught $name ($signo) signal");
        }
        $name = "m{$name}";
        $class = get_called_class();
        if ($found && method_exists($class, $name))
        {
            $class::$name();
        }
        else if (method_exists($class, $name = 'mSigUnknown'))
        {
            $class::$name($signo);
        }
        else
        {
            $debug && self::stDebug(self::D_INFO . 'Unhandled signal, exiting');
            self::$eventBase->loopBreak();
            exit;
        }
    }

    protected static function mSigInfo()
    {

    }

    protected static function mSigWinch()
    {
        
    }

    protected static function mSigChld()
    {
        $debug = self::stGetDebug($thread);
        while (0 < $pid = pcntl_waitpid(-1, $status, WNOHANG | WUNTRACED))
        {
            $debug && self::stDebug(self::D_INFO . "SIGCHLD is for pid #{$pid}");
            if ($pid > 0 && isset(self::$threadsByPids[$pid]))
            {
                if (isset(self::$threads[$threadId = self::$threadsByPids[$pid]]))
                {
                    $thread = self::$threads[$threadId];
                    $debug && self::stDebug(self::D_INFO . "SIGCHLD is for thread #{$threadId}");
                    if (!$thread->cleaning)
                    {
                        $thread->stopWorker();
                    }
                }
            }
        }
    }

    protected function stopWorker($wait = false, $signo = SIGTERM)
    {
        $res = false;
        if ($this->isForked)
        {
            if ($this->getIsAlive())
            {
                if ($this->debug)
                {
                    $do = ($signo == SIGSTOP || $signo == SIGKILL) ? 'Kill' : 'Stop';
                    $this->debug(self::D_INFO . "$do worker");
                }
                $this->sendSignalToChild($signo);
                if ($wait)
                {
                    $this->debug(self::D_INFO . 'Waiting for the child');
                    $pid = $this->child_pid;
                    if ($signo == SIGSTOP)
                    {
                        $i = 15;
                        usleep(1000);
                        do
                        {
                            $st = pcntl_waitpid($pid, $status, WNOHANG | WUNTRACED);
                            if ($st)
                            {
                                break;
                            }
                            usleep(100000);
                        }
                        while (--$i > 0);
                        if (!$st)
                        {
                            return $this->stopWorker(true, SIGKILL);
                        }
                    }
                    else
                    {
                        pcntl_waitpid($pid, $status, WUNTRACED);
                    }
                }
                $res = true;
            }
            $this->isForked = false;
        }
        if (!$this->cleaning)
        {
            $this->setState(self::STATE_WAIT);
        }
        return $res;
    }

    protected function killWorker($wait = false)
    {
        return $this->stopWorker($wait, SIGKILL);
    }

    protected function shutdown()
    {
        if ($this->isChild)
        {
            $this->debug(self::D_INFO . 'Child exit');
            $this->cleanup();
            exit;
        }
    }

    protected function debug($message)
    {
        if (!$this->debug)
        {
            return;
        }

        $time = mtoShell::getLogTime();
        $role = $this->isChild ? 'W' : 'M'; // Master|Worker
        $message = "{$time} [debug] [T{$this->id}.{$role}] #{$this->pid}: {$message}";

        echo $message;
        @ob_flush();
        @flush();
    }

    protected static function stDebug($message)
    {
        if (!self::stGetDebug($thread))
        {
            return;
        }

        $time = mtoShell::getLogTime();
        $role = $thread->isChild ? 'W' : 'M'; // Master|Worker
        $message = "{$time} [debug] [T-.{$role}] #{$thread->pid}: {$message}";

        echo $message;
        @ob_flush();
        @flush();
    }

    protected static function stGetDebug(&$thread = null)
    {
        $class = get_called_class();
        if (__CLASS__ === $class)
        {
            $class = key(self::$threadsByClasses);
        }
        if (empty(self::$threadsByClasses[$class]))
        {
            throw new mtoException("Couldn't find threads of type $class");
        }
        $thread = reset(self::$threadsByClasses[$class]);
        return $thread->debug;
    }

    public static function useForks()
    {
        return mtoShell :: hasForkSupport() && mtoShell :: hasLibEvent();
    }

}


