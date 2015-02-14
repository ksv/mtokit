<?php
/*
 *
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCollectConfCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $conf = mtoConf :: instance();
        $conf->loadConfig("config/mtosysconf.ini");
        $server_id = $conf->get("cdn_user", "my_id");
        $root = $conf->get("core", "root");

        $files = array_merge($conf->getSection("sysconf_all"), $conf->getSection("sysconf_" . $server_id));

        if (empty($files))
        {
            throw new mtoCliException("Configuration not found");
        }

        foreach ($files as $source => $file)
        {
            $target = $root . "/config/system/" . $server_id . "/" . $file;
            if (!file_exists($source))
            {
                $this->out($source . " do not exists in fs");
                continue;
            }
            if (!file_exists($target))
            {
                mtoFs :: mkDir(dirname($target));
                copy($source, $target);
                $this->out("New " . $target . " created");
            }
            else
            {
                if (md5_file($source) != md5_file($target))
                {
                    unlink($target);
                    copy($source, $target);
                    $this->out($target . " updated");
                }
            }
        }
        if (!empty($args['commit']))
        {
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, mtoConf :: instance()->get("svn", "login"));
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, mtoConf :: instance()->get("svn", "password"));
            svn_auth_set_parameter(SVN_AUTH_PARAM_DONT_STORE_PASSWORDS, true);
            svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE, true);
            
            $result = svn_commit("-- conf", array($root . "/config/system/" . $server_id));
            if ($result == false)
            {
                $this->out("SVN commit failed");
            }
            else
            {
                $this->out("SVN: result commited to revision:" . $result[0]);
            }
        }
    }
}