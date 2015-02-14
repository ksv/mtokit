<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoDbSphinxCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {

        $command_base = "/usr/local/bin/indexer";

        $commands = array();
        $out = false;
        $indexes = array();


        switch ($args['type'])
        {
            case "all":
                if ($locks = $this->isAnyLocked())
                {
                    foreach ($locks as $ind => $time)
                    {
                        $this->out("WARNING!!! " . $ind . " locked at " . date("Y-m-d H:i:s", $time));
                        $out = true;
                    }
                }
                $commands[] = $command_base . " --all --sighup-each";
                $indexes = array("order", "order_dev", "embro", "embro_dev");
                break;
            case "index":
                if (empty($args['index']))
                {
                    throw new mtoCliException("Indexes not defined");
                }
                foreach (explode(",", $args['index']) as $index)
                {
                    if (!empty($index))
                    {
                        if ($lock = $this->isLocked($index))
                        {
                            $this->out("WARNING!!! " . $index . " locked at " . date("Y-m-d H:i:s", $lock));
                            $out = true;
                            continue;
                        }
                        $commands[] = $command_base . " " . $index;
                        $indexes[] = $index;
                    }
                }
                break;
            case "delta":
                if (empty($args['delta']))
                {
                    throw new mtoCliException("Deltas not defined");
                }
                foreach (explode(",", $args['delta']) as $delta)
                {
                    if ($lock = $this->isLocked($delta))
                    {
                        $this->out("WARNING!!! " . $delta . " locked at " . date("Y-m-d H:i:s", $lock));
                        $out = true;
                        continue;
                    }
                    $commands[] = $command_base . " " . $delta . "_delta";
                    $commands[] = $command_base . " --merge " . $delta . " " . $delta . "_delta";
                    $indexes[] = $delta;
                }
                break;
            default:
                throw new mtoCliException("Unknown indexation type");
                break;
        }


        $this->out("Sphinx indexing started");
        $this->lockIndexes($indexes);
        foreach ($commands as $command)
        {
            $command .= " --rotate 2>&1";
            $this->out($command);
            exec($command, $result, $status);
            if ($status != 0)
            {
                $out = true;
            }
            sleep(5);
            $this->out("STATUS: " . $status);
            $this->out($result);
            $this->out("------------------------------");
            $result = "";
        }
        $this->unlockIndexes($indexes);
        $this->out("Sphinx indexing finished");
        if ($args['type'] == "delta")
        {
            if (!$out)
            {
                $this->quiet();
            }
        }

    }

    protected function lockIndexes($indexes)
    {
        foreach ($indexes as $index)
        {
            file_put_contents("var/locks/index." . $index, time());
        }
    }

    protected function unlockIndexes($indexes)
    {
        foreach ($indexes as $index)
        {
            if (file_exists("var/locks/index." . $index))
            {
                unlink("var/locks/index." . $index);
            }
        }
    }

    protected function isLocked($index)
    {
        if (file_exists("var/locks/index." . $index))
        {
            return file_get_contents("var/locks/index." . $index);
        }
        return 0;
    }

    protected function isAnyLocked()
    {
        $locks = array();
        $files = mtoFs :: ls("var/locks");
        foreach ($files as $file)
        {
            if (strpos($file, "index.") === 0)
            {
                $locks[str_replace("index.", "", $file)] = file_get_contents("var/locks/" . $file);
            }
        }
        return $locks;
    }

    function infoTitle()
    {
        return "Reindex sphinx source";
    }

    function infoDescription()
    {
        return "Must be executed as root.
        Used to full reindex, single index reindex or delta reindex with merge.
        Folder var/locks must exist for locking indexing process
        ";
    }

    function infoArguments()
    {
        return array(
            array('mapto' => "type", 'description' => "Type of reindexing (all|index|delta)")
        );
    }

    function infoOptions()
    {
        return array(
            array('name' => "index", 'description' => "Required for `index` type. Comma separated list of indexes to proceed"),
            array('name' => "delta", 'description' => "Required for `delta` type. Comma separated list of indexes to proceed. `index` and `index`_delta must be defined in sphinx")
        );
    }


}

