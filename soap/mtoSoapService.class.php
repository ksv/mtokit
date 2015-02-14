<?php
require_once("mtokit/config/mtoConf.class.php");
require_once("mtokit/profiler/mtoProfiler.class.php");
require_once("mtokit/wsdl/mtoWSDLCreator.class.php");
require_once("mtokit/soap/mtoSoapException.class.php");

class mtoSoapService
{
    static function callService($wsdl, $method, $args)
    {
        if (!extension_loaded("soap"))
        {
            throw new Exception("SOAP extension not available");
        }
        $params = array();
        if (mtoConf :: instance()->get("soap", "logging"))
        {
            $params['trace'] = true;
        }
        $params['connection_timeout'] = 10;
        $params['exceptions'] = true;
        ini_set("default_socket_timeout", 30);
        ini_set("soap.wsdl_cache", 1);
        set_time_limit(0);
        try
        {
            $client = new SoapClient($wsdl, $params);
            //var_dump($client);
        }
        catch (SoapFault $e)
        {
            //var_dump($e->getMessage());
            throw new Exception($e->getMessage());
        }
//        catch (Exception $e)
//        {
//            var_dump(get_class($e));
//            var_dump($e->getMessage());
//        }
        try {
            $result = $client->__soapCall($method, array('args' => $args));
            if (mtoConf :: instance()->get("soap", "logging"))
            {
                $message = "REQUEST:\n" . $client->__getLastRequestHeaders()."\n\n".$client->__getLastRequest()."\n\n\n\n";
                $message .= "RESPONSE:\n" . $client->__getLastResponseHeaders()."\n\n".$client->__getLastResponse()."\n\n\n\n";
                $message .= "\n\n\n\n\n\n";
                mtoProfiler :: instance()->logDebug($message, "soap_stream");
            }
        }
        catch (SoapFault $e)
        {
                $message = "REQUEST:\n" . $client->__getLastRequestHeaders()."\n\n".$client->__getLastRequest()."\n\n\n\n";
                $message .= "RESPONSE:\n" . $client->__getLastResponseHeaders()."\n\n".$client->__getLastResponse()."\n\n\n\n";
                $message .= "\n\n\n\n\n\n";
                mtoProfiler :: instance()->logError($message, "soap_error");
                //var_dump($e->getMessage());
                $result = array();
        }
        return $result;
    }

    static function createWsdl($conf)
    {
        $wsdl = new mtoWSDLCreator($conf['name'], $conf['url']);
        foreach ($conf['classes'] as $classname => $class)
        {
            $wsdl->addFile($class['path']);
            $wsdl->addURLToClass($classname, $class['url']);
        }
        $wsdl->createWSDL();
        $wsdl->saveWSDL(mtoConf :: instance()->getFile("soap", "wsdl_path") . '/' . $conf['out'], true);
    }

    static function getWsdl($wsdl)
    {
        return file_get_contents(mtoConf :: instance()->getFile("soap", "wsdl_path") . '/' . $wsdl);
    }

    protected function fail($message)
    {
        return array("status" => "fail", "message" => $message);
    }

    protected function done($result, $message="")
    {
        $result['status'] = "done";
        $result['message'] = $message;
        return $result;
    }

}