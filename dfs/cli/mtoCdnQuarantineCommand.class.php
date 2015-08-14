<?php
/*
 * --type=type_of_sharding
 * --section=number_of_section
 * [--skip-stat=1] to skip atime checking
 * [--dry-run=1] to not hard move files
 *
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoCdnQuarantineCommand extends mtoCliBaseCommand
{

    protected $args;
    protected $counter = 0;
    protected $total_size = 0;
    protected $moved_size = 0;

    function execute($args = array())
    {
        error_reporting(E_ALL);
        ini_set("display_errors", 1);
        if (empty($args['section']) || empty($args['type']))
        {
            throw new mtoCliException("Type and section sare required");
        }
        $this->args = $args;

        clearstatcache();
        $cdn = mtoCdnManager :: create($args['type']);

        $hosts = $cdn->getHostsBySection($args['section']);
        if (in_array($cdn->getOption("my_id"), $hosts))
        {
            throw new mtoCliException("Choosed section configured to be stored at this server");
        }

        $path = $cdn->getOption("path") . "/" . $args['section'];
        $this->args['timeout'] = $cdn->getOption("hold_timeout") * 3600 * 24;
        $this->args['path'] = $cdn->getOption("path");
        $this->args['qpath'] = $cdn->getOption("qarantine_path");

        $this->process_folder($path);

        $this->out("==========================");
        $this->out("==========================");
        $this->out("==========================");
        $this->out("FOLDER: " . $path . " processed");
        $this->out("TOTAL FILES: " . number_format($this->counter));
        $this->out("TOTAL SIZE: " . number_format($this->total_size));
        $this->out("MOVED SIZE: " . number_format($this->moved_size));

    }

    private function process_folder($path)
    {
        $handle = opendir($path);
        while ($file = readdir($handle))
        {
            if (!in_array($file, array(".", "..")) && strpos($path, ".svn") === false)
            {
                if (is_dir($path . "/" . $file))
                {
                    $this->process_folder($path . "/" . $file);
                }
                elseif (is_file($path . "/" . $file))
                {
                    $this->counter++;
                    $this->total_size += filesize($path . "/" . $file);
                    $delete = false;
                    if (isset($this->args['skip-stat']))
                    {
                        $delete = true;
                    }
                    $atime = fileatime($path . "/" . $file);
                    if ($atime < time() - $this->args['timeout'])
                    {
                        $delete = true;
                    }
                    if ($delete)
                    {
                        $this->moved_size += filesize($path . "/" . $file);
                        $new_full_path = str_replace($this->args['path'], $this->args['qpath'], $path . "/" . $file);
                        if (isset($this->args['dry-run']))
                        {
                            mtoProfiler :: instance()->logDebug(date("Y-m-d", $atime) . "\t" . $path . "/" . $file . "\t" . $new_full_path, "cdn_quarantine");
                        }
                        else
                        {
//                            mtoFs :: mkdir(dirname($new_full_path));
//                            rename($path . "/" . $file, $new_full_path);
                        }
                    }
                }
            }
        }
        closedir($handle);
    }

    function infoName()
    {
        return "cdn:quarantine";
    }
}