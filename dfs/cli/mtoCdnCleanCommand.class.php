<?php
/*
 * --type=type_of_sharding
 * --section=number_of_section
 * [--skip-stat=1] to skip atime checking
 *
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoCdnCleanCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        if (empty($args['section']) || empty($args['type']))
        {
            throw new mtoCliException("Type and section sare required");
        }
        $cdn = mtoCdnManager :: create($args['type']);

        $hosts = $cdn->getHostsBySection($args['section']);
        if (in_array($cdn->getOption("my_id"), $hosts))
        {
            throw new mtoCliException("Choosed section configured to be stored at this server");
        }

        $files = mtoFs :: findRecursive($cdn->getOption("path") . "/" . $args['section'], "f");
        $timeout = $cdn->getOption("hold_timeout") * 60;
        clearstatcache();
        foreach ($files as $file)
        {
            $delete = false;
            if (isset($args['skip-stat']))
            {
                $delete = true;
            }
            else
            {
                $stat = stat($file);
                if ($stat['atime'] < time()-$timeout)
                {
                    $delete = true;
                }
            }
            if ($delete)
            {
                unlink($file);
            }
        }
        
    }
}