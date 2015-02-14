<?php
/**
 * [--force=YYYYMMDDYYHH] - force time to execute
*/
class mtoCronManager
{
    protected $config = array();
    protected $server_id = 0;
    protected $jobs = array();
    
    function __construct()
    {
        mtoConf :: instance()->loadConfig("mtocron.ini");
        $this->config = mtoConf :: instance()->getSection("cron_jobs");
        $this->server_id = mtoConf :: instance()->get("cdn_user", "my_id");
    }
    
    static function create()
    {
        return new self();
    }
    
    function process($args)
    {
        if (isset($args['force']) && $args['force'])
        {
            $y = substr($args['force'], 0, 4);
            $m = substr($args['force'], 4, 2);
            $d = substr($args['force'], 6, 2);
            $h = substr($args['force'], 8, 2);
            $min = substr($args['force'], 10, 2);
            $time = strtotime($y."-".$m."-".$d." ".$h.":".$min.":05");
        }
        else
        {
            $time = time();
        }
        //$list = mtoApp :: getConf()->get("cli", "commands");
        foreach ($this->getJobs() as $job)
        {
            if ($this->checkTime($job, $time))
            {
                try
                {
                    $cmd = mtoCli :: createCommand($job['command']);
                }
                catch(Exception $e)
                {
                    var_dump($e->getMessage());
                    continue;
                }
                if ($job['user'] != "root")
                {
                    $owner = posix_getpwnam($job['user']);
                    posix_seteuid($owner['uid']);
                }
                $t = microtime(true);
                $cmd->set_return();
                if (!empty($job['args']['quiet']))
                {
                    $cmd->quiet();
                }
                try
                {
                    $cmd->execute($job['args']);
                }
                catch (Exception $e)
                {
                    var_dump("ERROR: " . $e->getMessage());
                }
                $cmd->out($job['command'] . " executed in " . round(microtime(true) - $t, 2) . " seconds");
                if ($job['user'] != "root")
                {
                    posix_seteuid(0);
                }
                //echo implode("\n", $cmd->get_strings());
                if (!$cmd->get_quiet())
                {
                    if (count($cmd->get_strings()))
                    {
                        $pairs = array();
                        foreach ($job['args'] as $key => $value)
                        {
                            $pairs[] = $key . "=" . $value;
                        }
                        $subj = "CRON: Host: " . $this->server_id . "; Command:" . $job['command'] . "; args: " . implode(" ", $pairs);
                        mtoToolkit :: instance()->send_plain_mail(mtoConf :: instance()->get("cron_settings", "notify_email"), $subj, implode("\n", $cmd->get_strings()));
                    }
                }
                mtoProfiler :: instance()->logDebug($job['command'] . " " . json_encode($job['args']), "cron/process");
            }
        }
    }
    
    private function getJobs()
    {
        if (empty($this->jobs))
        {
            if (empty($this->config[$this->server_id]))
            {
                return array();
            }
            foreach ($this->config[$this->server_id] as $entry)
            {
                $parts = explode(" ", $entry);
                $job = array();
                $job['minute'] = array_shift($parts);
                $job['hour'] = array_shift($parts);
                $job['day'] = array_shift($parts);
                $job['month'] = array_shift($parts);
                $job['weekday'] = array_shift($parts);
                $job['user'] = array_shift($parts);
                $job['command'] = array_shift($parts);
                $job['args'] = array();
                if (!empty($parts))
                {
                    foreach ($parts as $part)
                    {
                        if (!empty($part))
                        {
                            list($key, $value) = explode(":", $part);
                            $job['args'][$key] = $value;
                        }
                    }
                }
                $this->jobs[] = $job;
            }
        }
        return $this->jobs;
    }
    
    private function checkTime($job, $time)
    {
        $result = true;
        if ($job['minute'] != "*")
        {
            if (strpos($job['minute'], "/") !== false)
            {
                $parts = explode("/", $job['minute']);
                $result &= (intval(date("i", $time)) % intval($parts[1]) === 0);
            }
            else
            {
                $result &= intval($job['minute']) == intval(date("i", $time));
            }
        }
        if ($job['hour'] != "*")
        {
            $result &= intval($job['hour']) == intval(date("H", $time));
        }
        if ($job['day'] != "*")
        {
            $result &= intval($job['day']) == intval(date("j", $time));
        }
        if ($job['month'] != "*")
        {
            $result &= intval($job['month']) == intval(date("m", $time));
        }
        if ($job['weekday'] != "*")
        {
            if (intval($job['weekday']) == 7)
            {
                $result &= intval(date("w", $time)) == 0;
            }
            else
            {
                $result &= intval($job['weekday']) == intval(date("w", $time));
            }
        }
        return $result;
    }
    
    
    
    
}