<?php

require_once("mtokit/soap/mtoSoapService.class.php");

class mtoProfilerSoapService extends mtoSoapService
{

    /**
     *
     * @param array $args
     * @return array
     */
    function sendReplicationSms($args)
    {
        try
        {
             $numbers = explode(";", mtoConf :: instance()->get("profiler", "notify_sms"));
             foreach ($numbers as $number)
             {
                mtoToolkit :: instance()->smsSend($number, "Replication broken on " . $args['server_id'], "ALERT");
             }
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done(array(), "Client registered");
    }


}