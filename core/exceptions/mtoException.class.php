<?php
mtoClass :: import("mtokit/core/mtoBacktrace.class.php");


class mtoException extends Exception
{

    protected $original_message;
    protected $params = array();
    protected $file;
    protected $line;
    protected $backtrace;

    function __construct($message, $params = array(), $code = 0, $hide_calls_count = 0)
    {
        $this->original_message = $message;
        $this->params = $params;

        $this->backtrace = array_slice(debug_backtrace(), $hide_calls_count);

        foreach ($this->backtrace as $item)
        {
            if (isset($item['file']))
            {
                $this->file = $item['file'];
                $this->line = $item['line'];
                break;
            }
        }

        $message = $this->toNiceString();

        parent :: __construct($message, $code);
    }

    function getOriginalMessage()
    {
        return $this->original_message;
    }

    function getRealFile()
    {
        return $this->file;
    }

    function getRealLine()
    {
        return $this->line;
    }

    function getParams()
    {
        return $this->params;
    }

    function getParam($name)
    {
        if (isset($this->params[$name]))
        {
            return $this->params[$name];
        }
    }

    function getBacktrace()
    {
        return $this->backtrace;
    }

    function getNiceTraceAsString()
    {
        return $this->getBacktraceObject()->toString();
    }

    function getBacktraceObject()
    {
        return new mtoBacktrace($this->backtrace);
    }

    function toNiceString($without_backtrace = false)
    {
        $string = get_class($this) . ': ' . $this->getOriginalMessage() . PHP_EOL;
        if ($this->params)
        {
            $string .= 'Additional params: ' . PHP_EOL . mtoBacktrace :: var_export($this->params) . PHP_EOL;
        }
        if (!$without_backtrace)
            $string .= 'Backtrace: ' . PHP_EOL . $this->getBacktraceObject()->toString();
        return $string;
    }

    function __toString()
    {
        return $this->toNiceString();
    }

}
