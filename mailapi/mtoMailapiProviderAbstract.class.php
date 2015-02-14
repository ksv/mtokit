<?php
mtoClass :: import("mtokit/mailapi/mtoMailapiException.class.php");
mtoClass :: import("mtokit/mailapi/mtoMailapiInvalidException.class.php");

abstract class mtoMailapiProviderAbstract
{
    protected $provider = "";
    protected $config = array();
    protected $settings = array();
    protected $worker = null;
    protected $db = null;
    protected $devmode = false;
    protected $scope = "prod";



    function __construct($devmode = false)
    {
        $this->devmode = $devmode;
        if ($this->devmode)
        {
            $this->scope = "dev";
        }
        $this->config = mtoConf :: instance()->getSection("mailapi_core");
        $this->settings = mtoConf :: instance()->getSection("mailapi_" . $this->provider);
    }


    abstract function loadLists();
    abstract function hasError($result = array());
    abstract function createHook($list_id, $url, $actions = array(), $sources = array());
    abstract function loadHooks($list_id);
    abstract function removeHook($list_id, $url);
    abstract function subscribe($list_id, $email, $args = array());
    abstract function unsubscribe($list_id, $email, $args = array());
    abstract function remove($list_id, $email, $args = array());
    abstract function update($list_id, $email, $args = array());
    abstract function updateBulk($list_id, $data);
    abstract function batchSubscribe($list_id, $data);
    abstract function createField($list_id, $tag, $args = array());
    abstract function memberInfo($list_id, $email);
    abstract function loadFields();
    abstract function dumpList($list_id, $status = null);




    function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    function checkResult($result)
    {
        if ($err = $this->hasError($result))
        {
            $this->logError($err);
            if (in_array($err['code'], array('220', '502')))
            {
                throw new mtoMailapiInvalidException("Error: " . $err['code'] . " ::: " . $err['message']);
            }
            else
            {
                throw new mtoMailapiException("Error: " . $err['code'] . " ::: " . $err['message']);
            }
        }
    }

    function updateLocalHook($list_id, $url, $actions, $sources)
    {
        $info = array(
            'hook_list_id' => $list_id,
            'hook_url' => $url,
            'hook_actions' => implode(",", $actions),
            'hook_sources' => implode(",", $sources)
        );
        $hook = $this->getLocalHook($list_id, $url);
        if (!empty($hook['hook_id']))
        {
            $info['hook_id'] = $hook['hook_id'];
            $this->db->sql_update($this->config['hook_table'], $info, "hook_id");
        }
        else
        {
            $this->db->sql_insert($this->config['hook_table'], $info);
        }
    }

    function updateLocalList($info)
    {
        $local = $this->getLocalList($info['id']);
        if (!empty($local['list_id']))
        {
            if (isset($info['title'])) $local['list_title'] = $info['title'];
            if (isset($info['users'])) $local['list_user_count'] = $info['users'];
            if (isset($info['scope'])) $local['list_scope'] = $info['scope'];
            $this->db->sql_update($this->config['list_table'], $local, "list_id");
        }
        else
        {
            $local = array();
            $local['list_api_provider'] = $this->provider;
            $local['list_api_id'] = $info['id'];
            $local['list_title'] = $info['title'];
            $local['list_user_count'] = $info['users'];
            $local['list_scope'] = $info['scope'];
            $this->db->sql_insert($this->config['list_table'], $local);
        }
    }


    function getLocalHook($list_id, $url)
    {
        return $this->db->sql_getone("select * from {$this->config['hook_table']} where hook_list_id=? and hook_url=?", array($list_id, $url));
    }

    function getLocalList($id)
    {
        return $this->db->sql_getone("select * from {$this->config['list_table']} where list_api_provider=? and list_api_id=? and list_scope=?", array($this->provider, $id, $this->scope));
    }

    function getListByLocalName($name)
    {
        return $this->db->sql_getone("select * from {$this->config['list_table']} where list_api_provider=? and list_local_id=? and list_scope=?", array($this->provider, $name, $this->scope));
    }

    function getLocalLists()
    {
        return $this->db->sql_getall("select * from {$this->config['list_table']} where list_api_provider=? and list_scope=?", array($this->provider, $this->scope));
    }

    function getSignedLocalLists()
    {
        return $this->db->sql_getall("select * from {$this->config['list_table']} where list_api_provider=? and list_scope=? and list_local_id<>''", array($this->provider, $this->scope));
    }

    function getFirstLocalList($ids)
    {
        foreach ($ids as $key => $id)
        {
            $ids[$key] = "'" . $id . "'";
        }
        return $this->db->sql_getone("select * from {$this->config['list_table']} where list_api_provider=? and list_local_id in (?) and list_scope=? order by list_user_count limit 1", array($this->provider, $ids, $this->scope));
    }

    function logError($error)
    {
        $this->log("ERROR", array('CODE: ' . $error['code'], 'MESSAGE:' . $error['message']));
    }

    function logSuccess($command, $message)
    {
        $this->log("SUCCESS", array($command, $message));
    }

    function log($status, $args = array())
    {
        $message = array();
        $message[] = $this->provider;
        $message[] = $status;
        foreach ($args as $arg)
        {
            $message[] = $arg;
        }
        mtoProfiler :: instance()->logDebug(implode("\t", $message), "mail/mailapi");
    }
}