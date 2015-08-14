<?php
/*
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/queue/mtoQueue.class.php");

class mtoQueueProcessCommand extends mtoCliBaseCommand
{
    
    function execute($args = array())
    {
        if (empty($args['queue']))
        {
            throw new mtoException('queue not defined');
        }
        $limit = 0;
        if (!empty($args['limit']))
        {
            $limit = $args['limit'];
        }
        $count = mtoQueue :: create($args['queue'])->fetch($limit)->processQueue($limit);
        $this->out($count . " events executed");
    }

    function infoName()
    {
        return "queue:queue_process";
    }


    function infoTitle()
    {
        return "Process events from queue";
    }

    function infoDescription()
    {
        return "Process some events from single queue";
    }

    function infoOptions()
    {
        return array(
            array('name' => 'queue', 'required' => true, 'description' => "Name of queue"),
            array('name' => 'limit', 'description' => "Maximum number of events to execute", 'default' => 100)
        );
    }

    
    
}