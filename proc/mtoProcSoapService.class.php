<?php
mtoClass :: import("mtokit/soap/mtoSoapService.class.php");

class mtoProcSoapService
{
    /**
     *
     * @param array $args
     * @return array
     */
    function testMethod($args)
    {
        try
        {
            $result = "dsadsa";
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "File checked");
    }
    
}