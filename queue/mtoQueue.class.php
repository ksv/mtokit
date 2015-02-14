<?php
mtoClass :: import("mtokit/queue/connection/mtoQueueAabstractConnection.class.php");
class mtoQueue
{
    
    private $queue;
    private $queue_name;
    private $conn;
    private $client_conn;
    private $debug_conn;
    private static $instances = array();
    private $workers = array();
    private $config = array();
    
    
    const LIMIT = 100;
    
    function __construct($queue_name, $conn_name = null)
    {
        $this->config = mtoConf :: instance()->getSection("queue");
        $this->queue_name = $queue_name;
        if (!isset($this->config["queue_" . $queue_name]))
        {
            throw new mtoException("Unknown queue name: " . $queue_name);
        }
        $queue = $this->config["queue_" . $queue_name];
        $queue .= "_" . mtoConf :: instance()->get("core", "suffix");
        if (empty($conn_name))
        {
            $conn_name = $this->config['connection'];
        }
        $this->queue = $queue;
        $class = "mtoQueue" . mto_camel_case($conn_name) . "Connection";
        $conn_conf = mtoConf :: instance()->getSection("queue_" . $conn_name);
        mtoClass :: import("mtokit/queue/connection/" . $class . ".class.php");
        $this->conn = new $class($this->queue, $conn_conf);
        $this->client_conn = new $class("client_" . $this->queue, $conn_conf);
        if (!empty($this->config['debug']))
        {
            $this->debug_conn = new $class("debug_" . $this->queue, $conn_conf);
        }
    }
    
    function fetch($limit = 0)
    {
        if (empty($limit))
        {
            $limit = self :: LIMIT;
        }
        if ($this->conn->lock())
        {
            $count = 0;
            for ($i=0; $i<$limit; $i++)
            {
                $event = $this->pop();
                if ($event)
                {
                    $event['args']['worker_pid'] = posix_getpid();
                    $this->push($event['name'], $event['args'], array('local' => true));
                    $count++;
                }
                else
                {
                    break;
                }
            }
            $this->conn->unlock();
            $this->log("MOVE", $count . " events moved to local queue");
        }
        else
        {
            $this->log("LOCKED", "fetch");
        }
        return $this;
    }
    
    function processQueue($limit = 0)
    {
        $t = microtime(true);
        if (empty($limit))
        {
            $limit = self :: LIMIT;
        }
        $count = 0;
        for ($i=0; $i<$limit; $i++)
        {
            if ($event = $this->pop(array('local' => 1)))
            {
                if ($event['args']['worker_pid'] != posix_getpid())
                {
                    $this->log("FAIL", "foreign event found ".$event['args']['worker_pid']);
                    if (@pcntl_getpriority($event['args']['worker_pid']) !== false)
                    {
                        $this->push($event['name'], $event['args'], array('local' => true));
                        $this->log("FAILBACK", "foreign event moved to queue");
                        continue;
                    }
                }
                if ($this->config['debug'])
                {
                    $this->push($event['name'], $event['args'], array('debug' => true));
                }
                $this->getWorker($event['name'])->process($event['args']);
                $this->log("QUEUE", $event['name'], $event['args']);
                $count++;
            }
        }
        foreach ($this->workers as $worker)
        {
            $worker->commit();
        }
        $this->log("PROCESSALL", $count . " events executed from local queue", array('time' => microtime(true) - $t));
        return $count;
    }
    
    function createEvent($event, $args = array())
    {
        if ($this->config['enabled'])
        {
            $this->push($event, $args);
            $this->log("PUSH", $event, $args);
        }
        else
        {
            $this->getWorker($event)->process($args);
            $this->log("IMMEDIATE", $event, $args);
        }
        return $this;
    }
    
    function pop($args = array())
    {
        $conn = empty($args['local']) ? $this->conn : $this->client_conn;
        $data = $conn->pop();
        if ($data === false)
        {
            return false;
        }
        $event = array();
        $event['name'] = $data['event_name'];
        unset($data['event_name']);
        $event['args'] = $data;
        return $event;
    }
    
    function push($event, $params = array(), $args = array())
    {
        $conn = empty($args['local']) ? $this->conn : $this->client_conn;
        if (!empty($args['debug']))
        {
            $conn = $this->debug_conn;
        }
        $params['event_name'] = $event;
        return $conn->push($params);
    }
    
    function log($act, $name, $args = array())
    {
        if ($this->config['logging'])
        {
            $msg = array();
            if (!empty($args['time']))
            {
                $msg[] = "[" . round($args['time'], 2) . "]";
            }
            else
            {
                $msg[] = "[UNKN]";
            }
            $msg[] = $act . "[" . $this->queue . "]";
            $msg[] = $name;
            $msg[] = serialize($args);
            mtoProfiler :: instance()->logDebug(implode("\t", $msg), "queue/" . $this->queue_name);
        }
    }
    
    static function create($queue = null, $conn_name = null)
    {
        if (empty($queue))
        {
            $queue = "main";
        }
        if (!isset(self :: $instances[$queue]))
        {
            self :: $instances[$queue] = new self($queue, $conn_name);
        }
        return self :: $instances[$queue];
    }
    
    function getValue($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
    
    function getWorker($event)
    {
        if (isset($this->workers[$event]))
        {
            return $this->workers[$event];
        }
        if (!isset($this->config['worker_' . $event]))
        {
            throw new mtoException("Unknown queue worker: " . $event);
        }
        $this->createWorker($event);
        return $this->workers[$event];
    }
    
    private function createWorker($event)
    {
        $path = $this->config['worker_' . $event];
        $class = basename($path);
        mtoClass :: import($path . ".class.php");
        $obj = new $class();
        $obj->setParent($this);
        $this->workers[$event] = $obj;
    }
    
    
    
}