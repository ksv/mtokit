<?php
/*
 *  --scope=scope_of_configuration
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCompileConfCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $conf = mtoConf :: instance();
        if (empty($args['scope']))
        {
            throw new mtoCliException("Configuration scope not found");
        }
        $files = mtoFs :: ls($conf->val("core|confdir") . "/" . $args['scope']);
        $content = '<?php';
        foreach ($files as $file)
        {
            $c = file_get_contents($conf->val('core|confdir') . '/' . $args['scope'] . '/' . $file);
            $c = str_replace('<?php', '', $c);
            $content .= $c;
            $content .= "\n\n";
        }
        $filename = $conf->val("core|confdir") . '/conf.' . $args['scope'] . '.compiled.php';
        file_put_contents($filename, $content);
        $this->out($filename . " compiled");

        svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $conf->val("svn|login"));
        svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $conf->val("svn|password"));
        svn_auth_set_parameter(SVN_AUTH_PARAM_DONT_STORE_PASSWORDS, true);
        svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE, true);
        $result = svn_commit("-- conf", array($filename));
        if ($result === false)
        {
            $this->out("SVN commit failed");
        }
        else
        {
            $this->out($filename . " commited to revision: " . $result[0]);
        }
    }
}