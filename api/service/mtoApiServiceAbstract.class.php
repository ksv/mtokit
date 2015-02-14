<?php
abstract class mtoApiServiceAbstract
{
    protected $api;
    protected $version;

    abstract function authorize();
    
    
    function __construct(mtoApi $api)
    {
        $this->api = $api;
    }
    
    function hasMethod($method)
    {
        $m = "api" . mto_camel_case($method);
        return method_exists($this, $m);
    }

    function call()
    {
        $m = "api" . mto_camel_case($this->api->getMethod());
        return $this->$m();
    }
    
    
}