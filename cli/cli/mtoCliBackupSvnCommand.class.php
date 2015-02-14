<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCliBackupSvnCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $filename = "svn_" . date("Y-m-d_H-i");        
        
        $command = "/usr/local/bin/svnadmin dump /usr/local/codebase > " . mtoConf :: instance()->get("core", "root") . "/backup/svndump/" .$filename.".dump";
        exec($command, $result);
        $this->out($command);
        $this->out($result);
        chdir(mtoConf :: instance()->get("core", "root") . "/backup/svndump");
        exec("tar -jcf " . $filename . ".tar.bz2 " . $filename . ".dump", $result);
        $this->out($result);
        unlink($filename . ".dump");
        $this->out("SVN repo dumped");
        chdir(mtoConf :: instance()->get("core", "root"));

    }
}
