<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCliListCommandsCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $list = mtoApp :: getConf()->get("cli", "commands");
        foreach ($list as $key => $entry)
        {
            $cmd = mtoCli :: createCommand($key);
            $this->out(mtoCli :: red(mtoCli :: bold($key)) . " - " . $cmd->infoTitle());
        }
        $this->out("");
        $this->out("");
    }

    function infoTitle()
    {
        return "Output available commands";
    }

    function infoName()
    {
        return "list";
    }
}
