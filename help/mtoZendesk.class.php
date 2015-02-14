<?php
mtoClass :: initPsr();
use Zendesk\API\Client as ZAPI;
class mtoZendesk
{
    protected $conf;
    protected $config;
    protected $api;
    //620583572
    //fld 24988511

    function __construct()
    {
        $this->conf = mtoConf :: instance()->loadConfig("mtokit_credentials.ini");
        $this->config = $this->conf->getSection("pw_zendesk");

        $this->api = new ZAPI($this->config['domain'], $this->config['login']);
        $this->api->setAuth("token", $this->config['token']);
    }

    function getAllUsers()
    {
        return $this->api->users()->findAll();
    }


    function createTicket($ticket)
    {
        $t = $this->callApi("tickets", "import", $ticket);
        if ($t)
        {
            mtoProfiler :: instance()->logDebug($ticket['external_id'] . " created as " . $t['ticket']['id'], "helpdesk/zendesk_ticket");
        }
        return $t['ticket'];
    }

    function importUsers($users)
    {
        $r = $this->callApi("users", "createMany", $users);
        return $r;
    }

    function importTicket($ticket)
    {

    }



    function createUser($user)
    {
        $user['verified'] = true;
        $res = $this->callApi("users", "search", array('query' => "email:" . $user['email']));
        //var_dump($res);
        if ($res && $res['count'] == 1)
        {
            if (!$this->compareUser($user, $res['users'][0]))
            {
                $user['id'] = $res['users'][0]['id'];
                $u = $this->callApi("users", "update", $user);
                if ($u)
                {
                    mtoProfiler :: instance()->logDebug($user['external_id'] . " updated (".$res['users'][0]['id'].")", "helpdesk/zendesk_user");
                }
                return $u['user'];
            }
        }
        elseif ($res && $res['count'] > 1)
        {
            mtoProfiler :: instance()->logDebug($user['email'] . " failed to register: many records found", "helpdesk/zendesk_error");
            throw new mtoException("More one user found");
        }
        else
        {
            $u = $this->callApi("users", "create", $user);
            if ($u)
            {
                mtoProfiler :: instance()->logDebug($user['external_id'] . " created as " . $u['user']['id'], "helpdesk/zendesk_user");
            }
            return $u['user'];
            //var_dump("created");
            //var_dump($r);
        }
        //var_dump($res);
        //$res = $this->callApi("users", "create", $user);
    }

    function compareUser($local, $remote)
    {
        foreach ($local as $key => $value)
        {
            if (!isset($remote[$key]))
            {
                return false;
            }
            if ($value != $remote[$key])
            {
                //var_dump($key);
                //var_dump($value);
                //var_dump($remote[$key]);
                return false;
            }
        }
        return true;
    }

    function callApi($endpoint, $method, $args = array())
    {
        try
        {
            $call_result = $this->api->$endpoint()->$method($args);
            $result = json_decode(json_encode($call_result), true);
        }
        catch (Exception $e)
        {
            $info = $this->api->getDebug();
            if (!empty($info->lastResponseBody))
            {
                $debug_info = json_decode($info->lastResponseBody, true);
            }
            else
            {
                $debug_info = array();
            }
            mtoProfiler :: instance()->logDebug($info->lastResponseCode . " [".$endpoint.":".$method.":".json_encode($args)."] " . $info->lastRequestBody . " " . $info->lastResponseBody, "helpdesk/zendesk_error");
            //var_dump($info->lastResponseCode);
            //var_dump($debug_info);
            $result = false;
        }
        $info = $this->api->getDebug();
        mtoProfiler :: instance()->logDebug(print_r($info, true) . "\n\n===============================\n============================\n\n\n", "helpdesk/zendesk_exchange");
        return $result;
    }

}