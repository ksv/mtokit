<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCronCommand extends mtoCliBaseCommand
{
    protected $config = array();
    protected $server_id = 0;
    protected $jobs = array();


    function execute($args = array())
    {
        if (isset($args['state']))
        {
            return $this->fetchState($args);
        }
        mtoConf :: instance()->loadConfig("mtocron.ini");
        $this->config = mtoConf :: instance()->getSection("cron_jobs");
        $this->server_id = mtoConf :: instance()->get("cdn_user", "my_id");
        if (!empty($args['force-time']))
        {
            $y = substr($args['force-time'], 0, 4);
            $m = substr($args['force-time'], 4, 2);
            $d = substr($args['force-time'], 6, 2);
            $h = substr($args['force-time'], 8, 2);
            $min = substr($args['force-time'], 10, 2);
            $time = strtotime($y."-".$m."-".$d." ".$h.":".$min.":05");
        }
        else
        {
            $time = time();
        }

        foreach ($this->getJobs() as $job)
        {
            if ($this->checkTime($job, $time) || (!empty($args['force-command']) && $args['force-command'] == $job['command']))
            {
                try
                {
                    $cmd = mtoCli :: createCommand($job['command']);
                    $job['args'] = $cmd->assertArgs($job['args']);
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
                    $r = file_put_contents("var/locks/cron." . posix_getpid(), json_encode($job));
                    if ($r === false)
                    {
                        var_dump($job);
                    }
                    $db = mtoToolkit :: instance()->getDbConnection();
                    $db->execute("set @currentMode=?", array($job['command']));
                    $cmd->execute($job['args']);
                    unlink("var/locks/cron." . posix_getpid());
                }
                catch (Exception $e)
                {
                    unlink("var/locks/cron." . posix_getpid());
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
        $this->quiet();
    }

    function fetchState($args = [])
    {
        $list = mtoFs :: ls("var/locks");
        foreach ($list as $file)
        {
            if (preg_match('#^cron\.(\d+)$#', $file, $matches))
            {
                $info = json_decode(file_get_contents("var/locks/" . $file), true);
                $state = @pcntl_getpriority($matches[1]) === false ? "KILLED" : "ALIVE";
                if (isset($args['clean']))
                {
                    if ($state == "KILLED")
                    {
                        unlink("var/locks/" . $file);
                        $state = "REMOVED";
                    }
                }
                if (!empty($args['kill']))
                {
                    if ($state == "ALIVE" && $args['kill'] == $info['args']['queue'])
                    {
                        if (posix_kill($matches[1], SIGKILL))
                        {
                            $state = "SHOOTED";
                        }
                        else
                        {
                            $state = "ERROR";
                        }
                    }
                }
                $this->out($matches[1]."\t".$state . "\t" . $info['command']."\t".json_encode($info['args']));
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
            if (strpos($job['hour'], '/') !== false)
            {
                $parts = explode("/", $job['hour']);
                $result &= (intval(date("H", $time)) % intval($parts[1]) === 0);
            }
            else
            {
                $result &= intval($job['hour']) == intval(date("H", $time));
            }
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



    function infoName()
    {
        return "cron";
    }

    function infoTitle()
    {
        return "Execute commands by cron";
    }

    function infoDescription()
    {
        return "Execute commands";
    }

    function infoOptions()
    {
        return array(
            array('name' => 'force-time', 'description' => "Override current time with YYYYMMDDHHII value"),
            array('name' => "force-command", 'description' => "Force execute command"),
            array('name' => 'state', 'single' => true, 'descripton' => "List of current running jobs"),
            array('name' => 'clean', 'single' => true, 'description' => "Remove killed desciptors"),
            array('name' => 'kill', 'description' => "Name of queue to kill all running jobs")
        );
    }
}