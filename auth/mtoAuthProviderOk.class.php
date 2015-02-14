<?php

class mtoAuthProviderOk extends mtoAuthProviderAbstract
{
    protected $oauth_access_token = "http://api.odnoklassniki.ru/oauth/token.do";
    protected $oauth_authorize = "http://www.odnoklassniki.ru/oauth/authorize";
    protected $base_api = "http://api.odnoklassniki.ru/api";
    protected $name = "ok";
    protected $scope = "SET STATUS;VALUABLE ACCESS;PUBLISH TO STREAM";
    protected $public_key;

    public function __construct()
    {
        parent :: __construct();
        $this->application_key = $this->config['application_id'];
        $this->application_secret = $this->config['private_key'];
        $this->public_key = $this->config['public_key'];
    }

    public function authorize($hashback, $args = array())
    {
        $args['http_hashback'] = $hashback;
        $info = array(
            'client_id' => $this->application_key, 
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args), 
            'response_type' => "code",
            'scope' => $this->scope
        );

        $this->session->set('auth_hashback', $hashback);
        $this->redirect($this->oauth_authorize, $info);
    }

    public function fetchToken($args = array())
    {
        if (!$this->request->has("code"))
        {
            return false;
        }
        $args['http_hashback'] = $this->session->get('auth_hashback');

        $info = array(
            'client_id' => $this->application_key,
            'client_secret' => $this->application_secret,
            'code' => $this->request->get("code"),
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args), 
            'grant_type' => "authorization_code"
        );

        $result = $this->http_call($this->oauth_access_token, $info, 'POST');
        $result = json_decode($result, true);
        $result = array_merge($result, $this->getProfile($result['access_token']));
        
        if (isset($result['access_token']))
        {
            return array(
                'token' => $result['refresh_token'], 
                'access_token' => $result['access_token'],
                'access_expired' => 30*60,
                'user' => $result['uid'],
                'socid' => $result['uid'],
                'email' => null,
                'fname' => $result['first_name'],
                'lname' => $result['last_name'],
                'socid' => $result['uid']
            );
        }

        return false;
    }
    
    public function checkToken($token, $id)
    {
        
    }
    
    function refreshToken($token, $args = array())
    {
        $info = array(
            'client_id' => $this->application_key,
            'client_secret' => $this->application_secret,
            'grant_type' => "refresh_token",
            'refresh_token' => $token
        );
        $result = $this->http_call($this->oauth_access_token, $info, 'POST');
        $result = json_decode($result, true);
        return array('access_token' => $result['access_token'], 'access_expired' => 30*60);
    }
    
    function post($uri, $title, $text, $args = array())
    {
        $info = array(
            'application_key' => $this->public_key,
            'format' => "json",
            'linkUrl' => $uri,
            'comment' => strip_tags($text)
        );
        $info['sig'] = $this->sign($info, $this->access_token);
        $info['access_token'] = $this->access_token;
        $result = json_decode($this->http_call($this->base_api . '/share/addLink', $info), true);
        if (!empty($result['error_code']))
        {
            
            $this->log_error("share.addLink", $info, $result);
            if (LIMB_APP_MODE == "devel")
            {
                var_dump($info);
                var_dump($result);
                die();
            }
        }
    }
    
    function groupPost($uri, $title, $text, $args = array())
    {
        
    }
    

    public function getProfile($token)
    {
        $args = array(
            'application_key' => $this->public_key,
            'format' => "json",
        );
        $args['sig'] = $this->sign($args, $token);
        $args['access_token'] = $token;

        $result = $this->http_call($this->base_api . '/users/getCurrentUser', $args);
        $result = json_decode($result, true);
        return $result;
    }
    
    protected function sign($args, $token)
    {
        ksort($args);
        $parts = array();
        foreach ($args as $k => $v)
        {
            $parts[] = $k . "=" . $v;
        }
        return strtolower(md5(implode("", $parts).md5($token.$this->application_secret)));
    }
}