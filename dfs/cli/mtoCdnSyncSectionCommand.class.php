<?php
/**
 * --type=type_of_sharding
 * --section=number_of_section
 * --target=host_id
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoCdnSyncSectionCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        if (empty($args['section']) || empty($args['target']) || empty($args['type']))
        {
            throw new mtoCliException("Type, section and target are required");
        }
        $cdn = mtoCdnManager :: create($args['type']);
        //$command = "scp -qr " . $cdn->getOption("path") . "/".$args['section']." " . $cdn->getOption("login") . "@" . $cdn->getHostById($args['target']) . ":" . $cdn->getOption("path") . "/" . $args['section'] . " 2>&1";
        $command = "/usr/local/bin/rsync -a -h -L -k -K --delete --stats --partial ";
        $command .= $cdn->getOption("path") . "/" . $args['section'] . "/ ";
        $command .= $cdn->getHostById($args['target']) . "::section" . $args['section'];
        exec($command, $result);
        $this->out("Section " . $args['section'] . " copied to node " . $args['target']);
        $this->out("Result: ");
        $this->out($result);


    }
}