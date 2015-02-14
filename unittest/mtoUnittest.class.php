<?php
mtoClass :: import("mtokit/unittest/mtoUnittestCollector.class.php");

class mtoUnittest
{
    protected $collector;
    protected $db;
    protected $debug = false;

    function __construct()
    {
        $this->collector = mtoUnittestCollector :: instance();
        $this->db = mtoToolkit :: instance()->getDbConnection();
        $GLOBALS['debug'] = false;
    }

    function getBacktrace($trace)
    {
        unset($trace[0]['args']);
        unset($trace[0]['object']);
        //var_dump($trace[0]);
        return "(file:" . basename($trace[0]['file']) . ", line:" . $trace[0]['line'] . ")";
    }

    function assertEq($value1, $value2)
    {
        if ($value1 == $value2)
        {
            $this->collector->addPass();
        }
        else
        {
            $this->collector->addMessage($value1 . " is not equals to " . $value2 . $this->getBacktrace(debug_backtrace()), false);
            $this->collector->addFail();
        }
        $this->collector->addCase();
    }

    function assertIs($object, $class)
    {
        eval("\$is_a = \$object instanceof $class;");

        if ($is_a)
        {
            $this->collector->addPass();
        }
        else
        {
            $this->collector->addMessage(get_class($object) . " is not " . $class . $this->getBacktrace(debug_backtrace()), false);
            $this->collector->addFail();
        }
        $this->collector->addCase();
    }

    function assertId($value1, $value2)
    {
        if ($value1 === $value2)
        {
            $this->collector->addPass();
        }
        else
        {
            $this->collector->addMessage($value1 . " is not identical to " . $value2 . $this->getBacktrace(debug_backtrace()), false);
            $this->collector->addFail();
        }
        $this->collector->addCase();
    }

    function assertNull($value)
    {
        if (is_null($value))
        {
            $this->collector->addPass();
        }
        else
        {
            $this->collector->addMessage($value . " is not null " . $this->getBacktrace(debug_backtrace()), false);
            $this->collector->addFail();
        }
        $this->collector->addCase();
    }


    function dump($msg)
    {
        if ($this->debug)
        {
            var_dump($msg);
        }
    }

    function createController($name, $request_args = array())
    {
        return GenericController :: createForTest($name, $request_args);
    }
}





