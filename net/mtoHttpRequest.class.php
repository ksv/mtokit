<?php

mtoClass :: import("mtokit/core/mtoSet.class.php");
mtoClass :: import("mtokit/net/mtoUri.class.php");
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
mtoClass :: import("mtokit/net/mtoHttpUploadedFile.class.php");

class mtoHttpRequest extends mtoSet
{

    protected $__get = array();
    protected $__post = array();
    protected $__request = array();
    protected $__files = array();
    protected $__cookie = array();
    protected $__method = null;
    protected $__uri = null;
    protected $__server = array();

    use mtoSingletone;
    
    function __construct()
    {
        $this->__uri = new mtoUri($this->getRequestUrl());
        $this->__get = $_GET;
        $this->__post = $_POST;
        $this->__request = $_REQUEST;
        $this->__cookie = $_COOKIE;
        $this->__server = $_SERVER;
        $this->__files = $_FILES;

        $this->__method = isset($this->__server['REQUEST_METHOD']) ? $this->__server['REQUEST_METHOD'] : "GET";

        $this->import(array_merge($this->__get, $this->__post, $this->__request));
    }
    
    
    function export()
    {
        return array_merge($this->__get, $this->__post, $this->__request);
    }
    
    function dump()
    {
        return $this->export();
    }

    function set($name, $value)
    {
        parent :: set($name, $value);
        $_REQUEST[$name] = $value;
    }

    function getFile($name)
    {
        if (isset($this->__request[$name]))
        {
            if (in_array($name, array('import_url', 'upload_url', 'iurl')))
            {
                return mtoHttpUploadedFile :: createUrl($this->__request[$name]);
            }
            else
            {
                return mtoHttpUploadedFile :: createXhr($this->__request[$name]);
            }
        }
        if (isset($this->__files[$name]))
        {
            return mtoHttpUploadedFile :: createMultipart($this->__files[$name]);
        }
        return new mtoHttpUploadedFile();
    }

    function getMethod()
    {
        return $this->__method;
    }
    
    function getServer($var)
    {
        return isset($this->__server[$var]) ? $this->__server[$var] : null;
    }
    
    function hasServer($var)
    {
        return isset($this->__server[$var]);
    }
    
    function getCookie($var)
    {
        return isset($this->__cookie[$var]) ? $this->__cookie[$var] : null;
    }
    
    function hasCookie($var)
    {
        return isset($this->__cookie[$var]);
    }

    function setCookie($var, $value)
    {
        $this->__cookie[$var] = $value;
    }

    function getUri()
    {
        return $this->__uri;
    }

    function hasPost()
    {
        return $this->__method == "POST";
    }

    function getRawUrl()
    {
        return $this->getServer('REQUEST_URI');
    }
    
    function getRequestUrl()
    {
        $host = 'localhost';
        if (!empty($_SERVER['HTTP_HOST']))
        {
            $items = explode(':', $_SERVER['HTTP_HOST']);
            $host = $items[0];
            $port = isset($items[1]) ? $items[1] : null;
        }
        elseif (!empty($_SERVER['SERVER_NAME']))
        {
            $items = explode(':', $_SERVER['SERVER_NAME']);
            $host = $items[0];
            $port = isset($items[1]) ? $items[1] : null;
        }

        if (isset($_SERVER['HTTPS']) && !strcasecmp($_SERVER['HTTPS'], 'on'))
        {
            $protocol = 'https';
        }
        else
        {
            $protocol = 'http';
        }

        if (!isset($port) || $port != intval($port))
        {
            $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
        }

        if ($protocol == 'http' && $port == 80)
        {
            $port = null;
        }

        if ($protocol == 'https' && $port == 443)
        {
            $port = null;
        }

        $server = $protocol . '://' . $host . (isset($port) ? ':' . $port : '');

        if (isset($_SERVER['REQUEST_URI']))
        {
            $url = $_SERVER['REQUEST_URI'];
        }
        elseif (isset($_SERVER['QUERY_STRING']))
        {
            $url = basename($_SERVER['PHP_SELF']) . '?' . $_SERVER['QUERY_STRING'];
        }
        elseif (PHP_SAPI == 'cli')
        {
            $url = basename($_SERVER['PHP_SELF']);
        }
        else
        {
            $url = $_SERVER['PHP_SELF'];
        }

        return $server . $url;
    }



}

