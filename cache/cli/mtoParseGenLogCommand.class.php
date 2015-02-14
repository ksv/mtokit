<?php
/*
 * --log=log name
 * [--limit=n] - min counter
 *
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoParseGenLogCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        if (!isset($args['log']))
        {
            throw new mtoCliException("Logfile not defined");
        }
        if (!file_exists(mtoConf :: instance()->getFilename("var/log/" . $args['log'] . ".log")))
        {
            throw new mtoCliException("Logfile does not exist");
        }
        $src = file(mtoConf :: instance()->getFilename("var/log/" . $args['log'] . ".log"));
        $stat = array();
        foreach ($src as $entry)
        {
            list($time, $user, $scope, $id, $size, $params, $url, $referer) = explode("\t", $entry);
            if (strpos($url, "?") !== false)
            {
                $parts = explode("?", $url);
                $url = array_shift($parts);
            }
            if (!isset($stat[$url]))
            {
                $stat[$url] = 0;
            }
            $stat[$url]++;
        }

        foreach ($stat as $url => $count)
        {
            if (isset($args['limit']))
            {
                if ($count < $args['limit'])
                {
                    continue;
                }
            }
            $this->out($url . " = " . $count);
        }

    }
}