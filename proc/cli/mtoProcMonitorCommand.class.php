<?php
/**
 * --[cmds=cmd1+cmd2+cmdN] - cmd definitions
 * --[pids=pid1+pid2+pidN] - pid definitions
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoProcMonitorCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        try
        {
            if (!isset($args['cmds']) && !isset($args['pids']))
            {
                throw new mtoCliException("--cmds or --pids args required");
            }
            $cmds = array();
            $pids = array();
            if (isset($args['cmds']))
            {
                $clist = explode("+", $args['cmds']);
                foreach ($clist as $c)
                {
                    $com = mtoConf :: instance()->get("proc", "alive_cmd_" . $c);
                    if (!empty($com))
                    {
                        $cmds[] = $com;
                    }
                }
            }
            if (isset($args['pids']))
            {
                $plist = explode("+", $args['pids']);
                foreach ($plist as $p)
                {
                    $pid = mtoConf :: instance()->get("proc", "alive_pid_" . $p);
                    if (!empty($pid))
                    {
                        $pids[] = $pid;
                    }
                }
            }
            if (empty($cmds) && empty($pids))
            {
                throw new mtoCliException("Nothing to monitor");
            }
            if (!empty($cmds))
            {
                $list = shell_exec("ps -awx");
                foreach ($cmds as $cmd)
                {
                    if (!preg_match("#" . preg_quote($cmd) . "#", $list))
                    {
                        $mails = explode(";", mtoConf :: instance()->get("profiler", "notify_email"));
                        $server_id = mtoConf :: instance()->get("cdn_user", "my_id");
                        $lockdir = mtoConf :: instance()->get("core", "vardir") . "/locks";
                        mtoFs :: mkdir($lockdir);
                        foreach ($mails as $mail)
                        {
                            mail($mail, $cmd . " died", $cmd . " died");
                        }
                        if (!file_exists($lockdir . "/sms.lock"))
                        {
                            $numbers = explode(";", mtoConf :: instance()->get("profiler", "notify_sms"));
                            foreach ($numbers as $number)
                            {
                               mtoToolkit :: instance()->smsSend($number, $cmd . " died", "ALERT");
                            }
                            file_put_contents($lockdir . "/sms.lock", "1");
                        }
                        $p = popen($cmd, "r");
                        pclose($p);
                    }
                }
            }
        }
        catch (Exception $e)
        {
            throw new mtoCliException($e->getMessage());
        }


    }

    function infoName()
    {
        return "proc:proc_monitor";
    }
}