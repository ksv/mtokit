<?php
mtoClass :: import("mtokit/net/mtoHttpRequest.class.php");
mtoClass :: import("mtokit/net/mtoHttpSession.class.php");

abstract class mtoAuthProviderAbstract
{

    protected $request;
    protected $confObj;
    protected $config;
    protected $session;
    protected $name;
    protected $access_token;

    protected $application_key;
    protected $application_secret;
    protected $oauth_callback = "/auth/callback";
    protected $oauth_authorize;
    protected $oauth_access_token;
    

    abstract function authorize($hashback, $args = array());
    abstract function fetchToken($args = array());
    abstract function checkToken($token, $id);
    abstract function refreshToken($token, $args = array());
    abstract function post($uri, $title, $text, $args = array());
    abstract function groupPost($uri, $title, $text, $args = array());


    function __construct()
    {
        $this->confObj = mtoConf :: instance();
        $this->request = new mtoHttpRequest();
        $this->session = new mtoHttpSession();

        $this->config = $this->confObj->getSection("auth_" . $this->name . "_" . mtoConf :: instance()->get("auth", "scope"));
    }
    
    function setAccessToken($token)
    {
        $this->access_token = $token;
    }

    public function gen_hashback()
    {
        return md5($this->application_secret . time());
    }

    protected function redirect($target, $args = array())
    {
        header('Location: ' . $target . '?' . http_build_query($args));
    }

    protected function sign($args)
    {
        $sign = $this->application_secret;
        ksort($args);

        foreach ($args as $key => $value) {
            $sign .= $key . $value;
        }

        return md5($sign);        
    }

    protected function http_call($url, $args = array(), $method = 'GET')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    // return result
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);    // disallow redirection
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);           // timeout 5s

        switch ($method) 
        {
            case 'GET':
                curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($args));
                break;

            case 'POST':
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
                break;
            
            default:
                throw new mtoAuthException("Unknown HTTP request type");                
                break;
        }
    
        $data = curl_exec($ch);

        if (curl_errno($ch)) 
        {
            $exception = new mtoAuthException("Error while requesting API: " . curl_error($ch));
            curl_close($ch);
            throw $exception;
        }

        curl_close($ch);
        return $data;
    }
    
    protected function get_callback_uri($path, $args = array())
    {
        if (isset($args['callback_path']))
        {
            $path = $args['callback_path'];
        }
        $query = array();
        foreach ($args as $key => $arg)
        {
            if (strpos($key, 'http_') === 0)
            {
                $query[] = str_replace("http_", "", $key) . "=" . $arg;
            }
        }
        
        return $this->confObj->get("core", "http_root") . $path . "?" . implode("&", $query);
    }
    
    protected function log_error($method, $args, $result)
    {
        $message = get_class($this) . "\t" . $method . " FAILED\nArguments:\n";
        $message .= $this->log_join_array($args);
        $message .= "\n\n";
        $message .= "Responce:\n";
        $message .= $this->log_join_array($result);
        $message .= "\n\n\n";
        mtoProfiler :: instance()->logDebug($message, "social_error");
    }
    
    protected function log_join_array($arr)
    {
        $list = array();
        foreach ($arr as $key => $value)
        {
            if (is_array($value))
            {
                $list[] = $key . " = " . print_r($value, true);
            }
            else
            {
                $list[] = $key . " = " . $value;
            }
        }
        return implode("\n", $list);
    }
}
