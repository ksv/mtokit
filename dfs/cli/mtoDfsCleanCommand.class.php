<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoDfsManager.class.php");

class mtoDfsCleanCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        try
        {
            $result = mtoDfsManager :: create()->clear();
            $this->out("DONE: " . $result['message']);
        }
        catch (Exception $e)
        {
            throw new mtoCliException($e->getMessage());
        }


    }
}