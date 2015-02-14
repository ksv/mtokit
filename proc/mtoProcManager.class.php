<?php
mtoClass :: import("mtokit/soap/mtoSoapService.class.php");
mtoClass :: import("mtokit/proc/mtoProcSoapService.class.php");
class mtoProcManager
{

    function __construct()
    {
        
    }

    static function create($skey = null)
    {
        if (is_null(self :: $instance))
        {
            self :: $instance = new self($skey);
        }
        return self :: $instance;
    }

    function pushWsdl()
    {
//        $pattern = $this->getOption('allowed_ip_pattern');
//        if (!empty($pattern))
//        {
//            if (!preg_match($pattern, $_SERVER['REMOTE_ADDR']))
//            {
//                die("XML");
//            }
//        }
        header("Content-type: text/xml");
        echo mtoSoapService :: getWsdl("proc.wsdl");
        die();
    }

    function handleSoapRequest()
    {
        ini_set("soap.wsdl_cache", 0);
        $server = new SoapServer(mtoConf :: instance()->getFile("soap", "wsdl_path") . '/proc.wsdl');
        $server->setClass("mtoProcSoapService");
        $server->handle();
    }

}