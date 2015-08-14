<?php
/**
 * --type=type_of_sharding
 * --target=host_id
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoCdnSyncDataCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        if (empty($args['target']) || empty($args['type']))
        {
            throw new mtoCliException("Type and target are required");
        }
        $cdn = mtoCdnManager :: create($args['type']);
        //$command = "scp -qr " . $cdn->getOption("path") . "/".$args['section']." " . $cdn->getOption("login") . "@" . $cdn->getHostById($args['target']) . ":" . $cdn->getOption("path") . "/" . $args['section'] . " 2>&1";
        $command = "/usr/local/bin/rsync -a -h -L -k -K --delete --stats --partial ";
        if (!empty($args['exclude']))
        {
            $ex = explode(",", $args['exclude']);
            foreach ($ex as $e)
            {
                $command .= " --exclude=" . $e . " ";
            }
        }
        $command .= $cdn->getOption("path") . "/ ";
        $command .= $cdn->getHostById($args['target']) . "::data";
        exec($command, $result);
        $this->out("Full dataset copied to node " . $args['target']);
        $this->out("Result: ");
        $this->out($result);


    }

    function infoName()
    {
        return "cdn:sync_data";
    }
}