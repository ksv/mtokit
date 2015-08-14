<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/js/cli/mtoJsBuildCommand.class.php");
mtoClass :: import("mtokit/js/cli/mtoCssBuildCommand.class.php");

class mtoMakeReleaseCommand extends mtoCliBaseCommand
{
    
    function execute($args = array())
    {
        if (!empty($args['update']))
        {
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, mtoConf :: instance()->get("svn", "login"));
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, mtoConf :: instance()->get("svn", "password"));
            svn_auth_set_parameter(SVN_AUTH_PARAM_DONT_STORE_PASSWORDS, true);
            svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE, true);

            $res = svn_update(mtoConf :: instance()->get("core", "root"));

            if ($res === false)
            {
                throw new mtoException("SVN UPDATE FAILED");
            }
        }
        
        $c = new mtoJsBuildCommand();
        if (!empty($args['cron']))
        {
            $c->set_return();
        }
        $c->execute($args);
        if (!empty($args['cron']))
        {
            $this->out($c->get_strings());
        }
        
        $c = new mtoCssBuildCommand();
        if (!empty($args['cron']))
        {
            $c->set_return();
        }
        $c->execute($args);
        if (!empty($args['cron']))
        {
            $this->out($c->get_strings());
        }
        
        mtoFs :: rm("var/twig");
        
        file_put_contents("var/release.ini", date("YmdHis"));
        $this->out("release builded");
        
    }

    function infoName()
    {
        return "js:make_release";
    }
    
    
    
}
