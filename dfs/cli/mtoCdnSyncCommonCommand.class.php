<?php
/**
 * --type=type_of_sharding
 * --target=host_id
 * --[part=part_to_sync]
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoCdnSyncCommonCommand extends mtoCliBaseCommand
{
    protected $parts = array();

    function __construct()
    {
        $this->parts = mtoConf :: instance()->getSection("dfs_sync");
    }
    
    function execute($args = array())
    {
        if (empty($args['target']) || empty($args['type']))
        {
            throw new mtoCliException("Type and target are required");
        }
        $cdn = mtoCdnManager :: create($args['type']);
        if (!empty($args['part']))
        {
            if (!isset($this->parts[$args['part']]))
            {
                throw new mtoCliException("Selected part do not exist");
            }
            $command = "/usr/local/bin/rsync -a -h -L -k -K --delete --stats --partial ";
            $command .= $this->parts[$args['part']] . "/ ";
            $command .= $cdn->getHostById($args['target']) . "::common_" . $args['part'];
            exec($command, $result);
            $this->out("Part " . $args['part'] . " copied to node " . $args['target']);
            $this->out("Result: ");
            $this->out($result);
        }
        else
        {
            foreach ($this->parts as $part => $path)
            {
                $command = "/usr/local/bin/rsync -a -h -L -k -K --delete --stats --partial ";
                $command .= $path . "/ ";
                $command .= $cdn->getHostById($args['target']) . "::common_" . $part;
                exec($command, $result);
                $this->out("Part " . $part . " copied to node " . $args['target']);
                $this->out("Result: ");
                $this->out($result);
            }
        }


    }
}