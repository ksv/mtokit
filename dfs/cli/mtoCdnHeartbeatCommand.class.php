<?php
/**
 * --action=action to execute[master|slave|collect]
 */
mtoClass :: import("mtokit/soap/cli/mtoSoapBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoCdnHeartbeatCommand extends mtoSoapBaseCommand
{

    function execute($args = array())
    {
        if (empty($args['action']))
        {
            throw new mtoCliException("Nothing to execute");
        }
        if (!in_array($args['action'], array("master", "slave", "collect")))
        {
            throw new mtoCliException("Unknown command");
        }
        $table = mtoConf :: instance()->get("core", "heartbeat_table");
        $field = mtoConf :: instance()->get("core", "heartbeat_field");
        $warning = mtoConf :: instance()->get("core", "heartbeat_warning");
        switch ($args['action'])
        {
            case "master":
                $this->quiet();
                mtoDb :: execute("update `".$table."` set `".$field."`=".time());
            break;
            case "slave":
                $row1 = mtoDb :: fetchOneRow("show slave status");
                $row2 = mtoDb :: fetchOneRow("select * from `".$table."`");
                $io = isset($row1['Slave_IO_Running']) ? $row1['Slave_IO_Running'] : "No";
                $sql = isset($row1['Slave_SQL_Running']) ? $row1['Slave_SQL_Running'] : "No";
                $hb = isset($row2['hb']) ? $row2['hb'] : 0;
                if ($io != "Yes" || $sql != "Yes" || (time()-$hb) > ($warning * 60))
                {
                    $this->out("Replication broken");
                    $mails = explode(";", mtoConf :: instance()->get("profiler", "notify_email"));
                    $server_id = mtoConf :: instance()->get("cdn_user", "my_id");
                    $lockdir = mtoConf :: instance()->get("core", "vardir") . "/locks";
                    mtoFs :: mkdir($lockdir);
                    foreach ($mails as $mail)
                    {
                        mail($mail, "Replication broken on " . $server_id, "Replication broken on " . $server_id);
                    }
                    if (!file_exists($lockdir . "/sms.lock"))
                    {
                        mtoSoapService :: callService(mtoConf :: instance()->get("profiler.master_wsdl"), "sendReplicationSms", array('server_id' => $server_id));
                        file_put_contents($lockdir . "/sms.lock", "1");
                    }
                }
                else
                {
                    $this->quiet();
                }
            break;
            case "collect":
            break;
        }
    }


//    function execute($args = array())
//    {
//        if (empty($args['login']) || empty($args['password']))
//        {
//            throw new mtoCliException("Login and password are required");
//        }
//        try
//        {
//            $result = mtoDfsManager :: create()->register($args['login'], $args['password']);
//            $this->check_result($result);
//        }
//        catch (Exception $e)
//        {
//            throw new mtoCliException($e->getMessage());
//        }
//
//
//    }
}