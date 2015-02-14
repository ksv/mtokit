<?php

set_include_path(__DIR__ . '/../' . PATH_SEPARATOR . get_include_path());


require_once(__DIR__ . '/config/mtoConf.class.php');
require_once(__DIR__ . '/core/mtoClass.class.php');


$conf = mtoConf :: instance();
$conf->set("core", "__mtopath__", dirname(__DIR__));

mtoClass :: create();


mtoClass :: import("mtokit/core/mtoBacktrace.class.php");
mtoClass :: import("mtokit/core/exceptions/mtoException.class.php");
mtoClass :: import("mtokit/core/exceptions/mtoFileNotFoundException.class.php");
mtoClass :: import("mtokit/core/exceptions/mtoNotFoundException.class.php");
mtoClass :: import("mtokit/fs/mtoFs.class.php");
mtoClass :: import("mtokit/toolkit/mtoToolkit.class.php");
mtoClass :: import("mtokit/webapp/mtoApplication.class.php");





function mto_camel_case($str, $ucfirst = true)
{
    if (strpos($str, '_') === false)
    {
        return $ucfirst ? ucfirst($str) : $str;
    }

    $items = explode('_', $str);
    $len = sizeof($items);
    $first = true;
    $res = '';
    for ($i = 0; $i < $len; $i++)
    {
        $item = $items[$i];
        if (!$item || is_numeric($item))
        {
            $res .= '_' . $item;
        }
        elseif (!$first)
        {
            $res .= ucfirst($item);
        }
        else
        {
            $res .= $ucfirst ? ucfirst($item) : $item;
            $first = false;
        }
    }

    return $res;
}

function mto_under_scores($str)
{
    //caching repeated requests
    static $cache = array();
    if (isset($cache[$str]))
        return $cache[$str];

    $items = preg_split('~([A-Z][a-z0-9]+)~', $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $res = '';
    foreach ($items as $item)
    {
        $res .= ( $item == '_' || $item[0] == '_' ? '' : '_') . strtolower($item);
    }
    $res = substr($res, 1);
    $cache[$str] = $res;
    return $res;
}

function mto_humanize($str)
{
    return str_replace('_', ' ', mto_under_scores($str));
}

function __T($timer, $stop = false)
{
    if ($stop)
    {
        mtoProfiler :: instance()->timerStop($timer);
    }
    else
    {
        mtoProfiler :: instance()->timerStart($timer);
    }
}


function __L($message, $log = null)
{
    $msg = is_array($message) ? implode("\t", $message) : $message;
    if (!empty($_SERVER['HTTP_REFERER']))
    {
        $msg = $_SERVER['HTTP_REFERER'] . "\t" . $msg;
    }
    if (!empty($_SERVER['REQUEST_URI']))
    {
        $msg = $_SERVER['REQUEST_URI'] . "\t" . $msg;
    }
    if (!empty($_SERVER['HTTP_HOST']))
    {
        $msg = $_SERVER['HTTP_HOST'] . "\t" . $msg;
    }
    mtoProfiler :: instance()->logDebug($msg, $log);
}



function _D($var, $trace = false, $return=false, $nohtml = false)
{
    if ($trace)
    {
        foreach ($var as $k => $v)
        {
            unset($var[$k]['object']);
            unset($var[$k]['args']);
        }
    }
    static $calls = 1;
    if ($nohtml)
    {
        $html = print_r($var, true);
    }
    else
    {
        $html =  "<strong>$calls</strong>. (<pre>";
        $html .= htmlentities(print_r($var, true), ENT_QUOTES, 'utf-8');
        $html .= "\n\n\n</pre>)";
    }
    $calls++;
    if ($return)
    {
        return $html;
    } else
    {
        echo $html;
    }
}

function __D($var, $trace = false)
{
    if (defined('DEBUG_IP_ADDRESS') && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
    {
        _D($var, $trace);
    }
}

