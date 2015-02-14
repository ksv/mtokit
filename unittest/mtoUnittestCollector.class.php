<?php
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");

class mtoUnittestCollector
{
    private $output = array();
    private $passed = 0;
    private $failed = 0;
    private $cases = 0;
    private $msg_callback = null;
    private $storage = array();
    
    use mtoSingletone;


    function addPass()
    {
        $this->passed++;
    }

    function getPassed()
    {
        return $this->passed;
    }

    function addFail()
    {
        $this->failed++;
    }

    function getFailed()
    {
        return $this->failed;
    }

    function addCase()
    {
        $this->cases++;
    }

    function getCases()
    {
        return $this->cases;
    }

    function addMessage($message, $success)
    {
        $msg = ($success ? "DONE! " : "FAIL! ") . $message;
        $this->output[] = $msg;
        if (!is_null($this->msg_callback))
        {
            call_user_func_array($this->msg_callback, array($msg));
        }
    }

    function setMessageCallback($callback)
    {
        $this->msg_callback = $callback;
    }

    function set($key, $value)
    {
        $this->storage[$key] = $value;
    }

    function get($key)
    {
        return isset($this->storage[$key]) ? $this->storage[$key] : null;
    }
}