<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCliConsoleCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        mtoClass :: initPsr();
        $con = new \Boris\Boris('mto> ');
        $con->start();
    }

    function infoTitle()
    {
        return "Start Boris console";
    }

    function infoDescription()
    {
        return "Run Boris console with application environment";
    }
}
