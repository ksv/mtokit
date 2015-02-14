<?php
/*
 *
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");
mtoClass :: import("classes/xml/LocalSoapService.class.php");
mtoClass :: import("mtokit/soap/mtoSoapService.class.php");

class mtoCdnPingCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $toolkit = mtoToolkit :: instance();
        $db = $toolkit->getDbConnection();
        $hosts = mtoConf :: instance()->getSection("cdn_server_pool");
        $own_id = mtoConf :: instance()->get("cdn_user", "my_id");
        $params = array(
            'command' => "ping",
            'raw' => 1
        );
        
        foreach ($hosts as $id => $host)
        {
            list($img, $ctrl, $title) = explode("|", $host);
            if ($id != $own_id)
            {
                try
                {
                    $result = mtoSoapService :: callService("http://" . $ctrl . "/xml/local_wsdl", "processCommand", $params);
                    if (isset($result['status']) && $result['status'] == "done" && $result['message'] == "OK")
                    {
                        $db->execute("update ".mtoConf :: instance()->get("dfs.client_table")." set last_pinged=? where login=?", array(time(), $title));
                    }
                    $this->out('host' . $id . ' pinged');
                }
                catch (Exception $e)
                {
                    $this->out('host ' . $id . ' failed');
                    var_dump($e->getMessage());
                }
            }
        }
        $failed = $db->fetch("select * from ".mtoConf :: instance()->get("dfs.client_table")." where last_pinged < ?", array(time() - 600));
        if ($failed)
        {
            if (file_exists("var/down.ini"))
            {
                $list = parse_ini_file("var/down.ini");
            }
            else
            {
                $list = array();
            }
            $has_new = false;
            foreach ($failed as $fail)
            {
                if ($fail['login'] == "data")
                {
                    $fail['login'] = "cdn34";
                }
                $host = str_replace("cdn", "", $fail['login']);
                if (!in_array($host, array_keys($list)))
                {
                    $list[$host] = 1;
                    $toolkit->smsSend(mtoConf :: instance()->get("profiler", "notify_sms"), "CDN" . $host . " is down");
                    $has_new = true;
                }
            }
            if ($list)
            {
                $str = array();
                foreach ($list as $key => $value)
                {
                    $str[] = $key ." = " . $value;
                }
                file_put_contents("var/down.ini", implode("\n", $str));
                if ($has_new)
                {
                    $toolkit->getCache("memcache")->delete("cdn_broken_hosts_" . mtoConf :: instance()->get("core", "suffix"));
                }
            }
        }
    }
}