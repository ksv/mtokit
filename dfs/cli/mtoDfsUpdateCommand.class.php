<?php
/**
 * [--filters=filter1|filter2|filnetN] - queue filters
 * [--skip-event=1] - skip event recording
 */
mtoClass :: import("mtokit/soap/cli/mtoSoapBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoDfsManager.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoDfsUpdateCommand extends mtoSoapBaseCommand
{
    function execute($args = array())
    {
        try
        {
            $files = mtoDfsManager :: create()->queue();
            //$this->check_result($files, true);
            $filters = array();

            if (isset($args['filters']) && !empty($args['filters']))
            {
                $filter_list = explode("+", $args['filters']);
                foreach ($filter_list as $item)
                {
                    $class = "mtoDfs" . mto_camel_case($item) . "Filter";
                    mtoClass :: import("mtokit/dfs/filters/" . $class . ".class.php");
                    $filters[] = new $class();
                }
            }
            $result = mtoDfsManager :: create()->process($files['files'], $filters);


            if (isset($args['skip-event']) && $args['skip-event'])
            {
                $result = mtoDfsManager :: create()->record_last_sync(time());
            }
            else
            {
                $result = mtoDfsManager :: create()->record_last_event($result['last_id']);
            }
            //$this->check_result($result, true);
            //$this->quiet();

        }
        catch (Exception $e)
        {
            throw new mtoCliException($e->getMessage());
        }


    }
}