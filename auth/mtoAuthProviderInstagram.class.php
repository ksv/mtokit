<?php

class mtoAuthProviderInstagram extends mtoAuthProviderAbstract
{
    private static $scope = 'basic';
    protected $oauth_access_token = "https://api.instagram.com/oauth/access_token";
    protected $oauth_authorize = "https://api.instagram.com/oauth/authorize/";
    protected $oauth_callback = "";
    protected $base_api = "https://api.instagram.com/v1/";
    protected $name = "instagram";

    public function __construct()
    {
        parent :: __construct();
        $this->application_key = $this->config['application_id'];
        $this->application_secret = $this->config['application_secret'];
        $this->oauth_callback = $this->config['oauth_callback'];
    }

    public function authorize($hashback, $args = array())
    {
        $args['http_hashback'] = $hashback;
        $info = array(
            'client_id' => $this->application_key, 
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args), 
            'response_type' => 'code',
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
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args), 
            'code' => $this->request->get("code"),            
        );

        $result = $this->http_call($this->oauth_access_token, $info, 'POST'); 
        $result = json_decode($result, true);

        if (isset($result['access_token']))
        {
            return array(
                'token' => $result['access_token'], 
                'user_id' => $result['user']['id'],
                'user_name' => $result['user']['username'],
                'email' => null
            );
        }

        return false;
    }

    public function checkToken($token, $vk_id)
    {
        //
    }
    
    public function refreshToken($token, $args = array())
    {
        //
    }
    
    public function post($uri, $title, $text, $args = array())
    {
        //
    }

    function groupPost($uri, $title, $text, $args = array())
    {
        
    }
    
    public function getProfile($token, $instagram_id)
    {
        //
    }
  
}