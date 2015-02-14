<?php
mtoClass :: import("mtokit/mailapi/mtoMailapiProviderAbstract.class.php");

class mtoMailapiFactory
{

    static function create($provider, $devmode = false)
    {
        $conf = mtoConf :: instance()->loadConfig("mtokit/mailapi/config/mtokit_mailapi.ini");
        if (!array_key_exists($provider, $conf->getSection("mailapi_providers")))
        {
            throw new mtoException("Mailapi provider do not exists");
        }
        $class = "mtoMailapiProvider" . mto_camel_case($provider);
        mtoClass :: import("mtokit/mailapi/" . $class . ".class.php");
        return new $class($devmode);
    }
}