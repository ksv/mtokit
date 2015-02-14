<?php
mtoClass :: import("mtokit/mailapi/lib/MailChimp.class.php");
class mtoMailapiProviderMailchimp extends mtoMailapiProviderAbstract
{
    protected $provider = "mailchimp";
    protected $hook_actions = array(
        'subscribe' => false,
        'unsubscribe' => false,
        'profile' => false,
        'cleaned' => false,
        'upemail' => false,
        'campaign' => false
    );

    protected $hook_sources = array(
        'user' => false,
        'admin' => false,
        'api' => false
    );

    function __construct($devmode = false)
    {
        parent :: __construct($devmode);
        $this->worker = new MailChimp($this->devmode ? $this->settings['apikey_dev'] : $this->settings['apikey']);
    }

    function loadLists()
    {
        $result = $this->worker->lists();
        $this->checkResult($result);
        $list = array();
        foreach ($result['data'] as $entry)
        {
            $list[] = array('id' => $entry['id'], 'title' => $entry['name'], 'users' => $entry['stats']['member_count'], 'scope' => $this->scope);
        }
        return $list;
    }

    function loadHooks($list_id)
    {
        $result = $this->worker->listWebhooks($list_id);
        $this->checkResult($result);
        $list = array();
        foreach ($result as $entry)
        {
            $item = array('list_id' => $list_id, 'url' => $entry['url'], 'actions' => array(), 'sources' => array());
            foreach ($entry['actions'] as $key => $action)
            {
                if ($action)
                {
                    $item['actions'][] = $key;
                }
            }
            foreach ($entry['sources'] as $key => $source)
            {
                if ($source)
                {
                    $item['sources'][] = $key;
                }
            }
            $list[] = $item;
        }
        return $list;
    }

    function createHook($list_id, $url, $actions = array(), $sources = array())
    {
        $alist = $this->hook_actions;
        $slist = $this->hook_sources;
        foreach ($actions as $action)
        {
            $alist[$action] = true;
        }
        foreach ($sources as $source)
        {
            $slist[$source] = true;
        }
        $result = $this->worker->listWebhookAdd($list_id, $url, $alist, $slist);
        if (!$result)
        {
            $this->logError(array('code' => 0, 'message' => "Unable to create hook"));
            throw new mtoException("Cant create hook");
        }
    }

    function removeHook($list_id, $url)
    {
        $result = $this->worker->listWebhookDel($list_id, $url);
        $this->checkResult($result);
    }

    function subscribe($list_id, $email, $args = array())
    {
        $merge = array();
        foreach ($args as $key => $value)
        {
            if (strpos($key, "MERGE_") === 0)
            {
                $merge[str_replace("MERGE_", "", $key)] = $value;
            }
        }
        $result = $this->worker->listSubscribe($list_id, $email, $merge, "html", false, true, false, false);
        $this->checkResult($result);
        $this->logSuccess("SUBSCRIBE", $email . " subscribed to list " . $list_id);
    }

    function update($list_id, $email, $args = array())
    {
        $merge = array();
        foreach ($args as $key => $value)
        {
            if (strpos($key, "MERGE_") === 0)
            {
                $merge[str_replace("MERGE_", "", $key)] = $value;
            }
        }
        $result = $this->worker->listUpdateMember($list_id, $email, $merge, "html", true);
        $this->checkResult($result);
        $this->logSuccess("UPDATE", $email . " updated at list " . $list_id);
    }
    
    function updateBulk($list_id, $data)
    {
        
    }

    function unsubscribe($list_id, $email, $args = array())
    {
        $result = $this->worker->listUnsubscribe($list_id, $email, false, false, false);
        $this->checkResult($result);
        $this->logSuccess("UNSUBSCRIBE", $email . " unsubscribed from list " . $list_id);
    }
    
    function remove($list_id, $email, $args = array())
    {
        $result = $this->worker->listUnsubscribe($list_id, $email, true, false, false);
        $this->checkResult($result);
        $this->logSuccess("REMOVE", $email . " removed from list " . $list_id);
    }

    function batchSubscribe($list_id, $data)
    {
        $batch = array();
        foreach ($data as $entry)
        {
            $row = array();
            $row['EMAIL'] = $entry['user_email'];
            $row['FNAME'] = $entry['user_firstname'];
            $row['LNAME'] = $entry['user_login'];
            $batch[] = $row;
        }
        $result = $this->worker->listBatchSubscribe($list_id, $batch, false, true, false);
        $this->checkResult($result);
        $this->logSuccess("BATCH SUBSCRIBE", $result['add_count'] . " subscribers added, " . $result['update_count'] . " subscribers updated, " . $result['error_count'] . " errors");
    }

    function createField($list_id, $tag, $args = array())
    {
        if (isset($args['description']))
        {
            $description = $args['description'];
            unset($args['description']);
        }
        else
        {
            $description = "";
        }
        $result = $this->worker->listMergeVarAdd($list_id, $tag, $description, $args);
        $this->checkResult($result);
        $this->logSuccess("FIELD CREATED", $tag . " field created on list " . $list_id);
    }

    function memberInfo($list_id, $email)
    {
        $result = $this->worker->listMemberInfo($list_id, $email);
        $this->checkResult($result);
        $this->logSuccess("MEMBER INFO", "For: " . $email);
        return $result;
    }

    function hasError($result = array())
    {
        if ($this->worker->errorCode)
        {
            return array('code' => $this->worker->errorCode, 'message' => $this->worker->errorMessage);
        }
        else
        {
            return false;
        }
    }

    function dumpList($list_id, $status = null)
    {
        $result = $this->worker->callExportServer("list", array(
            'id' => $list_id,
            'status' => $status
        ));
        //$this->checkResult($result);
        return $result;
    }
    
    function loadFields()
    {
        throw new mtoException("not supported");
    }
}