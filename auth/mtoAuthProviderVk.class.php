<?php

class mtoAuthProviderVk extends mtoAuthProviderAbstract
{
    private static $scope = 'wall,offline,notes,email';
    protected $oauth_access_token = "https://api.vk.com/oauth/access_token";
    protected $oauth_authorize = "http://api.vk.com/oauth/authorize";
    protected $base_api = "https://api.vkontakte.ru/method/";
    protected $name = "vk";

    public function __construct()
    {
        parent :: __construct();
        $this->application_key = $this->config['application_id'];
        $this->application_secret = $this->config['application_secret'];
    }

    public function authorize($hashback, $args = array())
    {
        $args['http_hashback'] = $hashback;
        $info = array(
            'client_id' => $this->application_key, 
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args), 
            'display' => 'popup',
            'scope' => self :: $scope
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
        );

        $result = $this->http_call($this->oauth_access_token, $info);
        $result = json_decode($result, true);
        
        $profile = $this->getProfile($result['access_token'], $result['user_id']);

        if (isset($result['access_token']))
        {
            return array(
                'token' => $result['access_token'], 
                'user' => $result['user_id'],
                'email' => null,
                'fname' => $profile['first_name'],
                'lname' => $profile['last_name'],
                'socid' => $profile['uid'],
                'access_expired' => $result['expires_in'],
                'access_token' => $result['access_token']
            );
        }

        return false;
    }

    public function checkToken($token, $vk_id)
    {
        $args = array(
            'uid' => $vk_id,
            'access_token' => $token
        );

        $result = $this->http_call($this->base_api . 'isAppUser', $args);

        if (is_null($result) || isset($result['error']))
            return false;

        if (isset($result['response']))
            return ($result) ? true : false;
    }
    
    function refreshToken($token, $args = array())
    {
        return array('access_token' => $token, 'access_expired' => 0);
    }
    
    function post($uri, $title, $text, $args = array())
    {
        $info = array(
            'title' => $title,
            'text' => strip_tags($text),
            'access_token' => $this->access_token
        );
        $result = json_decode($this->http_call($this->base_api . 'notes.add', $info), true);
        if (!empty($result['error']))
        {
            $this->log_error("notes.add", $info, $result);
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
    

    public function getProfile($token, $vk_id)
    {
        $args = array(
            'uids' => $vk_id,
            'fields' => 'first_name,last_name,nickname,screen_name',
            'access_token' => $token
        );

        $result = $this->http_call($this->base_api . 'users.get', $args);
        $result = json_decode($result, true);

        if (isset($result['response'][0]))
            return $result['response'][0];

        if (is_null($result) || isset($result['error'])) 
            return false;
    }
    
    
}