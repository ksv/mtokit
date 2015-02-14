<?php
mtoClass :: import("mtokit/auth/mtoAuthProviderAbstract.class.php");
mtoClass :: import("mtokit/auth/mtoAuthException.class.php");
class mtoAuthFactory
{
    public static function create($service)
    {
        $conf = mtoConf :: instance();
        if (array_key_exists($service, $conf->getSection("auth_oauth_providers")))
        {
            $class = "mtoAuthProvider" . mto_camel_case($service);
            mtoClass :: import("mtokit/auth/" . $class . ".class.php");
            return new $class();
        }
        /*elseif (array_key_exists($service, $conf->getSection("auth_openid_providers")))
        {
            mtoClass :: import("mtokit/auth/mtoAuthProviderOpenid.class.php");
            return new mtoAuthProviderOpenid();
        }*/
        else
        {
            return false;
        }
    }
    
    public static function createByClass($class)
    {
        $conf = mtoConf :: instance();
        if (file_exists($conf->get("core", "__mtopath__") . "/mtokit/auth/" . $class . ".class.php"))
        {
            mtoClass :: import("mtokit/auth/" . $class . ".class.php");
            return new $class();
        }
        else
        {
            return false;
        }
    }
    
}