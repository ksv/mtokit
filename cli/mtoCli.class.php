<?php

class mtoCli
{
    static function parse_args($argv, $start = 1)
    {
        $args = array();
        $args['arguments'] = array();
        if (count($argv) > $start)
        {
            for ($i=$start; $i<count($argv); $i++)
            {
                if (strpos($argv[$i], "--") !== 0)
                {
                    $args['arguments'][] = $argv[$i];
                }
                $parts = explode("=", $argv[$i]);
                $args[str_replace("--", "", $parts[0])] = isset($parts[1]) ? $parts[1] : "";
            }
        }
        return $args;
    }

    static function init($filters = array())
    {
        mtoClass :: import("mtokit/filter_chain/mtoFilterChain.class.php");
        $application = new mtoFilterChain();
        $application->registerFilter("mtokit/webapp/filters/mtoInitFilter.class.php");
        foreach ($filters as $filter)
        {
            $application->registerFilter($filter);
        }
        $application->process();
        
    }

    static function createCommand($command)
    {
        $list = mtoApp :: getConf()->get("cli", "commands");
        if (!isset($list[$command]))
        {
            throw new mtoCliException("Commnad not found: " . $command);
        }
        $class = basename($list[$command]);
        $file = $list[$command] . ".class.php";
        mtoClass :: import($file);
        return new $class();
    }

    static function bold($text)
    {
        return "\033[1m" . $text . "\033[0m";
    }

    static function green($text)
    {
        return "\033[32m" . $text . "\033[0m";
    }

    static function red($text)
    {
        return "\033[31m" . $text . "\033[0m";
    }

    static function blue($text)
    {
        return "\033[34m" . $text . "\033[0m";
    }


}