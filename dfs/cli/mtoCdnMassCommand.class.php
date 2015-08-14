<?php
/*
 * --command=name of command
 * [--name-value] any arguments
 *
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");
mtoClass :: import("classes/xml/LocalSoapService.class.php");
mtoClass :: import("mtokit/soap/mtoSoapService.class.php");

class mtoCdnMassCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $hosts = mtoConf :: instance()->getSection("cdn_server_pool");
        $own_id = mtoConf :: instance()->get("cdn_user", "my_id");
        foreach ($hosts as $id => $host)
        {
            list($img, $ctrl, $title) = explode("|", $host);
            if ($id == $own_id)
            {
                $service = new LocalSoapService();
                $result = $service->processLocalCommand($args);
            }
            else
            {
                $result = mtoSoapService :: callService("http://" . $ctrl . "/xml/local_wsdl", "processCommand", $args);
            }
            $this->out("Host:" . $img);
            $this->out($result);
            $this->out("=====================");
        }
    }

    function infoName()
    {
        return "cdn:mass";
    }

    function infoArguments()
    {
        return array(
            ['mapto' => "command", 'required' => true, 'description' => "Action to execute"]
        );
    }
}