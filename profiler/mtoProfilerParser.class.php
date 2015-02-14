<?php
class mtoProfilerParser extends mtoProfilerTool
{
    private static $pattern = '#^\[(\d\d\d\d\-\d\d\-\d\d \d\d:\d\d:\d\d)\] - ([0-9\.,\(\)]+) - \[([a-z ]+)\] - \[([0-9\.]+)\] - \[(POST|GET)\] - \[([a-zA-Z_]*)\] - \[([a-zA-Z_]*)\] - \[(\w*)\] - \[([a-z0-9\~\|\.]*)\] - (.+) - (.*)$#U';


    public static function parseTimeLog($lines = false)
    {
        $arr = array();
        if (file_exists(dirname(__FILE__)."/../../log/pages.log"))
        {
            $file = file(dirname(__FILE__)."/../../log/pages.log");
            if ($lines)
            {
                return count($file);
            }
            if (is_array($file))
            {
                foreach ($file as $line)
                {
                    if (preg_match(self::$pattern, $line, $matches))
                    {
                        $pairs = explode("|", $matches[9]);
                        $cps = array();
                        foreach ($pairs as $pair)
                        {
                            list($name, $duration) = explode("~", $pair);
                            $cps[] = $name . "=" . $duration;
                        }
                        $arr[] = array(
                            'time' => $matches[1],
                            'used_time' => $matches[2],
                            'cache_type' => $matches[3],
                            'ip' => $matches[4],
                            'method' => $matches[5],
                            'mode' => $matches[6],
                            'action' => $matches[7],
                            'user' => $matches[8],
                            'checkpoints' => implode("<br />", $cps),
                            'uri' => $matches[10],
                            'referer' => $matches[11]
                        );
                    }
                    else
                    {
                        echo $line;
                        echo "<br />";
                    }
                }
            }
        }
        return $arr;
    }

    
}