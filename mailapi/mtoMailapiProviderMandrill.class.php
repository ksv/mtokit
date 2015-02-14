<?php
mtoClass :: import("mtokit/mailapi/lib/Mandrill.class.php");

class mtoMailapiProviderMandrill extends mtoMailapiProviderAbstract
{
    protected $provider = "mandrill";

    function __construct($devmode = false)
    {
        parent :: __construct($devmode);
        $this->worker = new Mandrill($this->devmode ? $this->settings['apikey_dev'] : $this->settings['apikey']);
    }

    function loadLists()
    {
        return array();
    }

    function loadHooks($list_id)
    {
        throw new mtoException("not implemented");
    }

    function createHook($list_id, $url, $actions = array(), $sources = array())
    {
        throw new mtoException("not implemented");
    }

    function removeHook($list_id, $url)
    {
        throw new mtoException("not implemented");
    }

    function subscribe($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function remove($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }

    function update($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function updateBulk($list_id, $data)
    {
        throw new mtoException("not implemented");
    }

    function unsubscribe($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }

    function batchSubscribe($list_id, $data)
    {
        throw new mtoException("not implemented");
    }

    function createField($list_id, $tag, $args = array())
    {
        throw new mtoException("not implemented");
    }

    function memberInfo($list_id, $email)
    {
        throw new mtoException("not implemented");
    }

    function hasError($result = array())
    {
        if ($this->worker->getLastErrorCode())
        {
            return array('code' => $this->worker->getLastErrorCode(), 'message' => $this->worker->getLastErrorMessage());
        }
        else
        {
            return false;
        }
    }

    function usersInfo()
    {
        $result = $this->worker->call("users", "info");
        $this->checkResult($result);
        return $result;
    }

    function sendMessage($tpl, $tpl_data, $message)
    {
        $result = $this->worker->call("messages", "send-template", array(
            'template_name' => $tpl,
            'template_content' => array($tpl_data),
            'message' => $message
        ));
        $this->checkResult($result);
        return $result;
    }
    
    function setTemplateCallback($callback)
    {
        
    }
    
    function loadFields()
    {
        throw new mtoException("not supported");
    }
    
    function dumpList($list_id, $status = null)
    {
        throw new mtoException("not supported");
    }
    
}