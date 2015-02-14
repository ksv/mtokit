<?php
/**
 * --clean=1 
 * --check=1
 * --service=name_of_service
 */

mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");
mtoClass :: import("mtokit/api/mtoApi.class.php");

class mtoProcDeployServiceCommand extends mtoCliBaseCommand
{
    
    function execute($args = array())
    {
        $cdn = mtoCdnManager :: create("user");
        if (!empty($args['clean']))
        {
            foreach ($cdn->getAllHosts() as $host)
            {
                $this->out(mtoApi :: callJson("/proc/clean", array(), array('host' => $host['ctl'])));
            }
            return;
        }
        if (!empty($args['check']))
        {
            if (file_exists("var/deploy") && file_exists("var/deploy/deploy"))
            {
                $service = file_get_contents("var/deploy/deploy");
                $conf = mtoConf :: instance()->loadConfig("config/mtosysservice.ini")->getSection("sysservice_" . $service);
                if (empty($conf) || empty($conf['restartcmd']))
                {
                    throw new mtoCliException("Configuration not found for service: " . $service);
                }
                if (!empty($conf['files']))
                {
                    foreach ($conf['files'] as $file)
                    {
                        if (!file_exists("var/deploy" . $file))
                        {
                            throw new mtoCliException("Deploy broken for service: service");
                        }
                    }
                    foreach ($conf['files'] as $file)
                    {
                        if (file_exists($file))
                        {
                            unlink($file);
                        }
                        copy("var/deploy" . $file, $file);
                        $this->out($file . " updated");
                    }
                }
                exec($conf['restartcmd'], $result);
                $this->out("Executing: " . $conf['restartcmd']);
                $this->out($result);
                mtoFs :: rm("var/deploy");
                mtoProfiler :: instance()->sendNotify($service . " deployed at " . mtoConf :: instance()->get("cdn_user", "my_id"), "", false);
            }
            else
            {
                //$this->out("NO DEPLOY");
                $this->quiet();
            }
            return;
        }
        if (empty($args['service']))
        {
            throw new mtoCliException("Service is not set");
        }
        $conf = mtoConf :: instance()->loadConfig("config/mtosysservice.ini")->getSection("sysservice_" . $args['service']);
        if (empty($conf) || empty($conf['restartcmd']))
        {
            throw new mtoCliException("Configuration not found for service: " . $args['service']);
        }
        $files = array();
        if (!empty($conf['files']))
        {
            foreach ($conf['files'] as $file)
            {
                $files[$file] = base64_encode(file_get_contents($file));
            }
        }
        foreach ($cdn->getAllHosts() as $host_id => $host)
        {
            if ($host_id != $cdn->getOption("my_id"))
            {
                $this->out("Host: " . $host['ctl']. "-------------");
                $res = mtoApi :: callJson("/proc/deploy/" . $args['service'], array(), array('host' => $host['ctl'], 'body' => $files));
                $this->out($res);
                $this->out("=========================");
            }
        }
        
    }
}