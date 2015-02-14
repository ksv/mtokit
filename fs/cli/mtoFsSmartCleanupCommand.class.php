<?php
/*
 * --path=path to root
 * [--skip-stat] do not use last access time
 * [--items] - list of item ids to remove
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoFsSmartCleanupCommand extends mtoCliBaseCommand
{
    protected $total_size = 0;
    protected $removed_size = 0;
    protected $counter = 0;
    protected $removed_by_item = 0;
    protected $removed_by_item_size = 0;

    function execute($args = array())
    {
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
        if (!file_exists($args['path']) || !is_dir($args['path']))
        {
            throw new mtoCliException("Path not defined exists");
        }
        clearstatcache();
        $this->cleanup_dir($args['path'], $args);
        $this->out("==========================");
        $this->out("==========================");
        $this->out("==========================");
        $this->out("FOLDER: " . $args['path'] . " processed");
        $this->out("TOTAL FILES: " . number_format($this->counter));
        $this->out("TOTAL SIZE: " . number_format($this->total_size));
        $this->out("REMOVED SIZE: " . number_format($this->removed_size));
        $this->out("REMOVED BY ITEM: " . number_format($this->removed_by_item));
        $this->out("REMOVED BY ITEM SIZE: " . number_format($this->removed_by_item_size));
    }

    private function cleanup_dir($dir, $args)
    {
        $handle = opendir($dir);
        while ($file = readdir($handle))
        {
            if (!in_array($file, array(".", "..", ".deleted")))
            {
                if (is_dir($dir . "/" . $file))
                {
                    $this->cleanup_dir($dir . "/" . $file, $args);
                }
                elseif (is_file($dir . "/" . $file))
                {
                    $this->counter++;
                    $this->total_size += filesize($dir . "/" . $file);
                    $delete = false;
                    if (isset($args['skip-stat']))
                    {
                        $delete = true;
                    }
                    if (fileatime($dir . "/" . $file) < (time() - mtoConf :: instance()->get("cache_args", "expire_days")*24*3600))
                    {
                        $delete = true;
                    }
                    if (strpos($dir, ".svn") !== false)
                    {
                        $delete = false;
                    }
                    if (strpos($dir, "/orders/") != false)
                    {
                        mtoProfiler :: instance()->logDebug("ORDER:" . $dir . "/" . $file, "cr_order");
                        $delete = false;
                    }
                    if (isset($args['items']) && $args['items'])
                    {
                        $pattern = "^\d+z\d+_(front|back)_\d+_(\d+)_\d_\d_.+\.jpg$";
                        $items = explode(",", $args['items']);
                        if (preg_match("#".$pattern."#", $file, $matches))
                        {
                            if (in_array($matches[2], $items))
                            {
                                $delete = true;
                                $this->removed_by_item++;
                                $this->removed_by_item_size += filesize($dir . "/" . $file);
                                $this->out("REMOVED_BY_ITEM_CONDITION: " . $dir . "/" . $file);
                            }
                        }
                    }
                    if ($delete)
                    {
                        $this->removed_size += filesize($dir . "/" . $file);
                        mtoProfiler :: instance()->logDebug("REMOVE:" . date("Y-m-d H:i:s", fileatime($dir . "/" . $file)) . ":" . $dir . "/" . $file, "cr_remove");
                        unlink($dir . "/" . $file);
                    }
                    if ($this->counter % 10000 == 0)
                    {
                        $this->out($this->counter . " files processed. Current target is: " . $dir . "/" . $file);
                    }
                    if ($this->counter % 1000000 == 0)
                    {
                        $this->out("TOTAL FILES: " . number_format($this->counter));
                        $this->out("TOTAL SIZE: " . number_format($this->total_size));
                        $this->out("REMOVED SIZE: " . number_format($this->removed_size));
                        $this->out("REMOVED BY ITEM: " . number_format($this->removed_by_item));
                        $this->out("REMOVED BY ITEM SIZE: " . number_format($this->removed_by_item_size));
                    }
                }
            }
        }
        closedir($handle);
    }

    function infoTitle()
    {
        return "Remove files by atime";
    }

    function infoDescription()
    {
        return "Remove files by atime";
    }

    function infoArguments()
    {
        return array(
            array ('mapto' => "path", 'description' => "Path to remove")
        );
    }

    function infoOptions()
    {
        return array(
            array('name' => "skip-stat", 'single' => true, 'description' => "Skip atime checks. Remove all"),
            array('name' => "items", 'description' => "Comma separated list of items to force remove")
        );
    }
}