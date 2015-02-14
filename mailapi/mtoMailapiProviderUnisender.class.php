<?php
mtoClass :: import("mtokit/mailapi/lib/UniSender.class.php");

class mtoMailapiProviderUnisender extends mtoMailapiProviderAbstract
{
    protected $provider = "unisender";

    function __construct($devmode = false)
    {
        parent :: __construct($devmode);
        $this->worker = new UniSender($this->devmode ? $this->settings['apikey_dev'] : $this->settings['apikey']);
    }

    function loadLists()
    {
        $result = $this->decode($this->worker->getLists());
        $this->checkResult($result);
        $list = array();
        foreach ($result['result'] as $entry)
        {
            $list[] = array('id' => $entry['id'], 'title' => $entry['title'], 'users' => 0, 'scope' => $this->scope);
        }
        return $list;
    }
    
    function loadFields()
    {
        $result = $this->decode($this->worker->getFields());
        $this->checkResult($result);
        return $result['result'];
    }

    function loadHooks($list_id)
    {
        throw new mtoException("not supported");
    }

    function createHook($list_id, $url, $actions = array(), $sources = array())
    {
        throw new mtoException("not supported");
    }

    function removeHook($list_id, $url)
    {
        throw new mtoException("not supported");
    }

    function subscribe($list_id, $email, $args = array())
    {
        $fields = array();
        $fields['email'] = $email;
        foreach ($args as $key => $value)
        {
            if (strpos($key, "MERGE_") === 0)
            {
                $fields[str_replace("MERGE_", "", $key)] = $value;
            }
        }
        $result = $this->decode($this->worker->subscribe(array(
            'list_ids' => $list_id,
            'fields' => $fields,
            'double_optin' => 3,
            'overwrite' => 2
        )));
        $this->checkResult($result);
        $this->logSuccess("SUBSCRIBE", $email . " subscribed to list " . $list_id);
    }

    function delete($email)
    {
        $this->update(null, $email, array('delete' => 1));
        $this->logSuccess("DELETE", $email);
    }

    function update($list_id, $email, $args = array())
    {
        $fields = array();
        $fields['email'] = $email;
        foreach ($args as $key => $value)
        {
            if (strpos($key, "MERGE_") === 0)
            {
                $fields[str_replace("MERGE_", "", $key)] = $value;
            }
            if (in_array($key, array("email_list_ids", "delete", "email_status", "email_excluded_list_ids", "phone")))
            {
                $fields[$key] = $value;
            }
        }
        $result = $this->decode($this->worker->importContacts(array(
            'field_names' => array_keys($fields),
            'data' => array(array_values($fields)),
            'force_import' => 1,
            'double_optin' => 1
        )));
        //var_dump($result);
        $this->checkResult($result);
        $this->logSuccess("UPDATE", $email . " updated at list " . $list_id);
        return $result;
    }

    function deleteBulk($emails)
    {
        $fields = array("email", "delete");
        $data = array();
        foreach ($emails as $email)
        {
            $data[] = [$email, 1];
            if (count($data) >= 450)
            {
                $result = $this->decode($this->worker->importContacts(array(
                    'field_names' => $fields,
                    'data' => $data
                )));
                var_dump($result);
                $data = array();
            }
        }
        if (count($data) > 0)
        {
            $result = $this->decode($this->worker->importContacts(array(
                'field_names' => $fields,
                'data' => $data
            )));
            //var_dump($result);
        }
    }
    
    function updateBulk($list_id, $data)
    {
        if (count($data) <= 0)
        {
            return;
        }
        $fields = array();
        foreach ($data[0] as $key => $value)
        {
            if (strpos($key, "MERGE_") === 0)
            {
                $fields[] = str_replace("MERGE_", "", $key);
            }
            else
            {
                $fields[] = $key;
            }
        }
        $send_data = array();
        $mails = array();
        foreach ($data as $row)
        {
            $mails[] = $row['email'];
            $send_data[] = array_values($row);
        }


        $result = $this->decode($this->worker->importContacts(array(
            'field_names' => $fields,
            'data' => $send_data,
            'force_import' => 1,
            'double_optin' => 1
        )));
        $this->checkResult($result);
        $this->logSuccess("UPDATE", "[".implode(",", $mails)."] updated at list " . $list_id);
        
    }

    function unsubscribe($list_id, $email, $args = array())
    {
        $result = $this->decode($this->worker->exclude(array(
            'contact_type' => "email",
            'contact' => $email,
            'list_ids' => $list_id
        )));
        $this->checkResult($result);
        $this->logSuccess("UNSUBSCRIBE", $email . " unsubscribed from list " . $list_id);
    }
    
    function remove($list_id, $email, $args = array())
    {
        $result = $this->decode($this->worker->unsubscribe(array(
            'contact_type' => "email",
            'contact' => $email,
        )));
        $this->checkResult($result);
        $this->logSuccess("REMOVE", $email . " removed from list " . $list_id);
    }

    function batchSubscribe($list_id, $data)
    {
        throw new mtoException("not supported");
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
        if (isset($result['error']))
        {
            return array('code' => $result['error'], 'message' => $result['code']);
        }
        else
        {
            return false;
        }
    }

    private function decode($data)
    {
        if ($data === false)
        {
            return false;
        }
        return json_decode($data, true);
    }

    function dumpAll($fields = array())
    {
        $data = array();
        $offset = 0;
        while (true)
        {
            $result = $this->decode($this->worker->exportContacts(array(
                'field_names' => $fields,
                'offset' => $offset,
                'limit' => 1000,
            )));
            if ($result === false)
            {
                throw new mtoException("API CALL FAILED");
            }
            if (isset($result['result']['data']) && is_array($result['result']['data']) && count($result['result']['data']))
            {
                foreach ($result['result']['data'] as $row)
                {
                    $data[] = $row;
                }
                $offset += 1000;
            }
            else
            {
                break;
            }
        }
        return $data;
    }
    
    function dumpList($list_id, $status = null)
    {
        if ($status == "subscribed")
        {
            $status = "active";
        }
        if ($status == "cleaned")
        {
            $status = "blocked";
        }
        
        $rs = array();
        
        $offset = 0;
        
        while (true)
        {
            $result = $this->decode($this->worker->exportContacts(array(
                'list_id' => $list_id,
                'field_names' => array('email', 'ULOGIN'),
                'offset' => $offset,
                'limit' => 1000,
                'email_status' => $status
            )));
            if ($result === false)
            {
                var_dump("API CALL FAILED");
                throw new mtoException("API CALL FAILED");
            }
            //mtoProfiler :: instance()->logDebug(print_r($result, true), "debug/unisender");
            if (empty($rs))
            {
                $rs = array_merge($rs, array($result['result']['field_names']));
            }
            if (isset($result['result']['data']) && is_array($result['result']['data']) && count($result['result']['data']))
            {
                $rs = array_merge($rs, $result['result']['data']);
                $offset += 1000;
            }
            else
            {
                break;
            }
        }
        
        return $rs;
    }
}