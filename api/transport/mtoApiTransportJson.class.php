<?php
mtoClass :: import("mtokit/api/transport/mtoApiTransportAbstract.class.php");

class mtoApiTransportJson extends mtoApiTransportAbstract
{
    function decode($post = "")
    {
        return json_decode($post, true);
    }
    
    function encode($data = array())
    {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}