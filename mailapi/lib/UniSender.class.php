<?php
mtoClass :: import("mtokit/net/mtoRemoteRequest.class.php");

class UniSender
{

    protected $ApiKey;
    protected $Encoding = 'UTF8';
    protected $RetryCount = 5;
    protected $Timeout = 120;

    function __construct($ApiKey, $Encoding = 'UTF8', $RetryCount = 4, $Timeout = null)
    {
        $this->ApiKey = $ApiKey;

        if (!empty($Encoding))
        {
            $this->Encoding = $Encoding;
        }

        if (!empty($RetryCount))
        {
            $this->RetryCount = $RetryCount;
        }

        if (!empty($Timeout))
        {
            $this->Timeout = $Timeout;
        }
    }

    function __call($Name, $Arguments)
    {
        if (!is_array($Arguments) || empty($Arguments))
        {
            $Params = array();
        }
        else
        {
            $Params = $Arguments[0];
        }

        return $this->callMethod($Name, $Params);
    }

    function subscribe($Params)
    {
        $Params = (array) $Params;

        if (empty($Params['request_ip']))
        {
            $Params['request_ip'] = $this->getClientIp();
        }

        return $this->callMethod('subscribe', $Params);
    }

    protected function decodeJSON($JSON)
    {
        return json_decode($JSON);
    }

    protected function getClientIp()
    {
        if (!empty($_SERVER["REMOTE_ADDR"]))
        {
            $Result = $_SERVER["REMOTE_ADDR"];
        }
        else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
        {
            $Result = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }
        else if (!empty($_SERVER["HTTP_CLIENT_IP"]))
        {
            $Result = $_SERVER["HTTP_CLIENT_IP"];
        }
        else
        {
            $Result = "";
        }

        if (preg_match('/([0-9]|[0-9][0-9]|[01][0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[0-9][0-9]|[01][0-9][0-9]|2[0-4][0-9]|25[0-5])){3}/', $Result, $Match))
        {
            return $Match[0];
        }

        return $Result;
    }

    protected function iconv(&$Value, $Key)
    {
        $Value = iconv($this->Encoding, 'UTF8//IGNORE', $Value);
    }

    protected function mb_convert_encoding(&$Value, $Key)
    {
        $Value = mb_convert_encoding($Value, 'UTF8', $this->Encoding);
    }

    protected function callMethod($MethodName, $Params = array())
    {
        if ($this->Encoding != 'UTF8')
        {
            if (function_exists('iconv'))
            {
                array_walk_recursive($Params, array($this, 'iconv'));
            }
            else if (function_exists('mb_convert_encoding'))
            {
                array_walk_recursive($Params, array($this, 'mb_convert_encoding'));
            }
        }

        $Params = array_merge((array) $Params, array('api_key' => $this->ApiKey));

        $args = array(
            'post' => 1,
            'connecttimeout' => 10,
            'timeout' => 120
        );
        
//        $ContextOptions = array(
//            'http' => array(
//                'method' => 'POST',
//                'header' => 'Content-type: application/x-www-form-urlencoded',
//                'content' => http_build_query($Params),
//            )
//        );

        if ($this->Timeout)
        {
            //$ContextOptions['http']['timeout'] = $this->Timeout;
            $args['timeout'] = $this->Timeout;
        }

        $RetryCount = 0;
        $this->RetryCount = 5;
        //$Context = stream_context_create($ContextOptions);

        do
        {
            $Host = $this->getApiHost($RetryCount);
            //$Result = file_get_contents($Host . $MethodName . '?format=json', FALSE, $Context);
            try
            {
                $Result = mtoRemoteRequest :: fetchCurl($Host . $MethodName . '?format=json', $Params, $args);
            } 
            catch (mtoException $e) 
            {
                if (defined("IN_CLI"))
                {
                    var_dump("FAILED");
                    var_dump($MethodName);
                    var_dump($Params);
                    var_dump($e->getMessage());
                }
                mtoProfiler :: instance()->logDebug("Attempt: " . $RetryCount . ", method: " . $MethodName . ", args: " . json_encode($Params), "mail/mailapi_us_failed_call");
                $Result = false;
            }
            $RetryCount++;
        }
        while ($Result === false && $RetryCount < $this->RetryCount);

        return $Result;
    }

    protected function getApiHost($RetryCount = 0)
    {
        if ($RetryCount % 2 == 0)
        {
            return 'http://api.unisender.com/ru/api/';
        }
        else
        {
            return 'http://www.api.unisender.com/ru/api/';
        }
    }

}