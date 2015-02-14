<?php
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");
class mtoDfsCdnFilter
{
    private $cdn;


    function __construct()
    {
        $this->cdn = mtoCdnManager :: create("user");
    }

    function filter($filename)
    {
        if (strpos($filename, "/users/") !== false)
        {
            $parts = explode("/", $filename);
            for ($i=0; $i<count($parts); $i++)
            {
                if ($parts[$i] == "users")
                {
                    if (isset($parts[$i+1]) && is_numeric($parts[$i+1]))
                    {
                        $section = intval($parts[$i+1]);
                        $hosts = $this->cdn->getHostsBySection($section);
                        if (!in_array($this->cdn->getOption("my_id"), $hosts))
                        {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}