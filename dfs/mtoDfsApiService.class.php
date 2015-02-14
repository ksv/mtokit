<?php
mtoClass :: import("mtokit/api/service/mtoApiServiceAbstract.class.php");
mtoClass :: import("mtokit/dfs/mtoDfsManager.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");

class mtoDfsApiService extends mtoApiServiceAbstract
{
    protected $version = "1.0";
    protected $dfs;
    protected $cdn;
    
    function __construct(mtoApi $api)
    {
        parent :: __construct($api);
        $this->dfs = mtoDfsManager :: create();
        $this->cdn = mtoCdnManager :: create("user");
    }
    
    function authorize()
    {
        if ($this->dfs->isMaster())
        {
            $this->dfs->login($this->api->getParam('login'), $this->api->getParam('password'));
            try
            {
                $this->dfs->assert();
            } 
            catch (Exception $e) 
            {
                throw new mtoApiException("Client authorization failed", array(), mtoApi :: ERR_NOT_AUTHORIZED);
            }
        }
        else
        {
            if (empty($_SERVER['REMOTE_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], $this->cdn->getAllIps()))
            {
                throw new mtoApiException("Server authorization failed", array(), mtoApi :: ERR_NOT_AUTHORIZED);
            }
        }
    }
    
    function apiQueue()
    {
        return $this->dfs->getClientQueue();
    }
    
    function apiRecordEvent()
    {
        return $this->dfs->setLastEvent(array('lastevent' => $this->api->getParam('event_id')));
    }
    
    function apiRecordSync()
    {
        return $this->dfs->setLastSync(array('lastsync' => $this->api->getParam("sync")));
    }
    
    function apiDelete()
    {
        $filename = $this->dfs->getRoot() . "/" . $this->api->getParam("file");
        if (file_exists($filename))
        {
            unlink($filename);
        }
    }
    
    function apiFetch()
    {
        $filename = $this->dfs->getRoot() . "/" . $this->api->getParam("file");
        mtoFs :: rcp("http://" . $this->dfs->getMasterHost() . "/" . $this->api->getParam("file"), $filename);
    }
}