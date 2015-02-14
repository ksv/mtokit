<?php
mtoClass :: import("mtokit/api/transport/mtoApiTransportAbstract.class.php");

class mtoApiTransportPlain extends mtoApiTransportAbstract
{
    function decode($post = "")
    {
        
    }
    
    function encode($data = array())
    {
        $parts = array();
        foreach($data as $key => $value)
        {
            $parts[] = $key .= ":" . $value;
        }
        return implode(";", $parts);
    }
}