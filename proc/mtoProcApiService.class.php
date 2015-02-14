<?php
mtoClass :: import("mtokit/api/service/mtoApiServiceAbstract.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoProcApiService extends mtoApiServiceAbstract
{
    protected $version = "1.0";
    protected $cdn;
    
    function __construct(mtoApi $api)
    {
        parent :: __construct($api);
        $this->cdn = mtoCdnManager :: create("user");
    }
    
    function authorize()
    {
        if (empty($_SERVER['REMOTE_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], $this->cdn->getAllIps()))
        {
            throw new mtoApiException("Server authorization failed", array(), mtoApi :: ERR_NOT_AUTHORIZED);
        }
    }
    
    function apiDeploy()
    {
        $service = $this->api->getObjectId();
        if (empty($service))
        {
            throw new mtoApiException("Service not defined");
        }
        $conf = mtoConf :: instance()->loadConfig("config/mtosysservice.ini")->getSection("sysservice_" . $service);
        if (empty($conf) || empty($conf['restartcmd']))
        {
            throw new mtoApiException("Configuration not found for service: " . $service);
        }
        if (file_exists("var/deploy"))
        {
            throw new mtoApiException("ERROR: Another deploy statred");
        }
        mtoFs :: mkdir("var/deploy");
        foreach ($this->api->getBody() as $file => $content)
        {
            mtoFs :: mkdir("var/deploy" . dirname($file));
            file_put_contents("var/deploy" . $file, base64_decode($content));
        }
        file_put_contents("var/deploy/deploy", $service);
        mtoProfiler :: instance()->sendNotify(mtoConf :: instance()->get("cdn_user", "my_id") . ". " . $service . " deploy started", "-");
        return array("deploy started");
    }
    
    function apiClean()
    {
        mtoFs :: rm("var/deploy");
        return array("Deploy data cleaned");
    }
    
}