<?php
class mtoRemoteRequest
{
    private $transports = array("curl", "socket", "stream");
    private $transport;
    
    
    function __construct($transport)
    {
        $this->transport = $transport;
    }
    
    function load($uri, $args = array(), $params=array())
    {
        $method = "load" . mto_camel_case($this->transport);
        return $this->$method($uri, $args, $params);
    }
    
    function loadCurl($uri, $args = array(), $params = array())
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $uri);
        $defaults = array(
            'timeout' => 30,
            'returntransfer' => 1,
            'ssl_verifypeer' => false,
            'ssl_verifyhost' => false,
            'connecttimeout' => 3,
            'dns_cache_timeout' => 5
        );
        if (!empty($args))
        {
            $defaults['post'] = 1;
            $defaults['postfields'] = http_build_query($args);
        }
        foreach (array_merge($defaults, $params) as $k => $v)
        {
            curl_setopt($curl, constant('CURLOPT_' . strtoupper($k)), $v);
        }
        $result = curl_exec($curl);
        if (curl_errno($curl) > 0 || curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200)
        {
            mtoProfiler :: instance()->logDebug($uri . "::" . curl_getinfo($curl, CURLINFO_HTTP_CODE) . "::" . curl_errno($curl) . "::" . curl_error($curl), "debug/curl_fail");
            curl_close($curl);
            throw new mtoException("Load failed");
        }
        curl_close($curl);
        return $result;
    }
    
    static function fetchCurl($uri, $args = array(), $params = array())
    {
        $obj = new self("curl");
        return $obj->load($uri, $args, $params);
    }
}