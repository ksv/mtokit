<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');


class mtoJsTools extends mtoAbstractTools
{

    function jsUnserialize($serialized)
    {
        $parts = explode(";", $serialized);
        $data = array();
        foreach ($parts as $part)
        {
            if (!empty($part) && strpos($part, "=") !== false)
            {
                list($key, $value) = explode("=", $part);
                $value = str_replace("~eq", "=", $value);
                $value = str_replace("~cm", ";", $value);
                $data[$key] = $value;
            }
        }
        return $data;
    }
    
    function decodeModelDsn($dsn)
    {
        $data = array();
        $parts = explode("/", $dsn);
        foreach ($parts as $part)
        {
            if (strpos($part, ":") === false)
            {
                continue;
            }
            list($k, $v) = explode(":", $part);
            switch ($k)
            {
                case 'c':
                    $data['class'] = $v;
                break;
                case 'm':
                    $data['method'] = $v;
                break;
                case 'rm':
                    $data['render_method'] = $v;
                break;
                case 'wm':
                    if (strpos($v, "+") !== false)
                    {
                        list($method, $wrap) = explode("+", $v);
                        $data['wrapped_method'] = $method;
                        $data['wrapped_wrap'] = $wrap;
//                        $msg = array();
//                        $msg[] = $_SERVER['REQUEST_URI'];
//                        $msg[] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
//                        $msg[] = json_encode($_REQUEST);
//                        mtoProfiler :: instance()->logDebug(implode("\t", $msg), "debug/jstools");
                    }
                break;
                case 'i':
                    $data['id'] = $v;
                break;
                case 't':
                    $data['tpl'] = $v;
                break;
                case 'b':
                    $data['tpl'] = "blocks/" . $v;
                break;
                case 'vb':
                    $data['view_base'] = $v;
                break;
                case 'arg':
                    list($key, $value) = explode("=", $v);
                    $data[$key] = $value;
                break;
            }
        }
        return $data;
    }


}