<?php
/*
 * --action=action to do
 * --log=log filename
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/profiler/mtoProfiler.class.php");

class mtoProfilerLogToolCommand extends mtoCliBaseCommand
{
    private $rotate_hold = 3;
    
    function execute($args = array())
    {
        ini_set("memory_limit", "2G");
        $toolkit = mtoToolkit :: instance();
        
        if (empty($args['action']))
        {
            throw new mtoCliException("Action not defined");
        }
        if (empty($args['log']))
        {
            throw new mtoCliException("Log filename not defined");
        }
        $log = mtoConf :: instance()->get("profiler", "log_folder") . "/" . $args['log'] . ".log";
        if (!file_exists($log) || !is_file($log))
        {
            throw new mtoCliException("log file not found");
        }
        $method = "exec" . mto_camel_case($args['action']);
        if (!method_exists($this, $method))
        {
            throw new mtoCliException("Action not found");
        }
        if (!empty($args['hold']))
        {
            $this->rotate_hold = intval($args['hold']);
        }
        $this->$method($log);
    }
    
    function execFreq($log)
    {
        $list = file($log);
        $ts = 0;
        $c = 0;
        foreach ($list as $line)
        {
            $items = explode("\t", $line);
            $lts = strtotime($items[0]);
            $c++;
            if (date("YmdHi", $lts) != $ts)
            {
                if (!empty($ts))
                {
                    $this->out(date("Y-m-d H:i", $lts) . " ::: " . $c);
                }
                $c = 0;
                $ts = date("YmdHi", $lts);
            }
        }
    }
    
    function execTruncate($log)
    {
        $fp = fopen($log, "w");
        fclose($fp);
        $thid->out($log . " truncated");
    }
    
    function execRotate($log)
    {
        if (file_exists($log."." . $this->rotate_hold))
        {
            unlink($log . "." . $this->rotate_hold);
        }
        for ($i=$this->rotate_hold-1; $i>=1; $i--)
        {
            if (file_exists($log . "." . $i))
            {
                rename($log . "." . $i, $log . "." . ($i+1));
            }
        }
        rename($log, $log . ".1");
        $this->out($log . " rotated");
    }
    
    
    
    
    
}