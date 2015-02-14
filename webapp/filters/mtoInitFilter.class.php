<?php


class mtoInitFilter implements mtoInterceptingFilter
{

    function run($filter_chain)
    {
        $conf = mtoConf :: instance();
        mtoClass :: import("mtokit/core/toolkit/mtoCoreTools.class.php");
        mtoClass :: import("mtokit/net/toolkit/mtoNetTools.class.php");
        mtoClass :: import("mtokit/webapp/toolkit/mtoWebappTools.class.php");
        mtoToolkit :: merge(new mtoCoreTools());
        mtoToolkit :: merge(new mtoNetTools());
        mtoToolkit :: merge(new mtoWebappTools());

        mtoClass :: import("mtokit/dte/mtoDTE.class.php");

        $toolkit = mtoToolkit :: instance();
        $profiler = $toolkit->getProfiler();

        $profiler->setRequest($toolkit->getRequest());
        $profiler->setLogLevel(constant("mtoProfiler::".$conf->get("profiler", "log_level")));
        $profiler->setLogFolder($conf->getFile("profiler", "log_folder"));


        date_default_timezone_set($conf->get("core", "timezone"));
        error_reporting(E_ALL);
        ini_set('magic_quotes_gpc', 0);
        ini_set('register_globals', 0);

        ini_set("log_errors", 1);
        ini_set("error_log", $conf->get("core", "root") . "/var/log/php_error.log");
        if ($conf->get("core", "appmode") == "debug")
        {
            ini_set("display_errors", 1);
        }
        else
        {
            ini_set("display_errors", 0);
        }


        $filter_chain->next();


    }


}