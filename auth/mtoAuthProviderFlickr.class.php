<?php
class mtoAuthProviderFlickr extends mtoAuthProviderAbstract
{
    
    protected $name = "flickr";
    protected $oauth_access_token = null;
    protected $oauth_authorize = "http://flickr.com/services/auth/";
    protected $rest_api = "http://api.flickr.com/services/rest/";
    
    public function __construct()
    {
        parent :: __construct();
        $this->application_key = $this->config['consumer_key'];
        $this->application_secret = $this->config['consumer_secret'];
    }
    

    public function authorize($hashback, $args = array())
    {
        return false;
    }

    public function getLoginUrl()
    {
        $args = array(
            'api_key' => $this->application_key,
            'perms' => 'read',
        );
        $args['api_sig'] = $this->sign($args);
        return $this->oauth_authorize . '?' . http_build_query($args);
    }

    public function fetchToken($args = array())
    {
        $args = array(
            'method' => 'flickr.auth.getToken',
            'api_key' => $this->application_key,
            'format' => 'json',
            'frob' => $this->http_call->get("frob")
        );
        $args['api_sig'] = $this->sign($args);
        $result = $this->http_call($this->rest_api, $args);
        $result = substr($result, 14, strlen($result) - 15);

        return $result;
    }

    public function checkToken($token, $id)
    {
        return false;
    }
    
    function refreshToken($token, $args = array())
    {
        
    }
    
    function post($uri, $title, $text, $args = array())
    {
        
    }
    
    function groupPost($uri, $title, $text, $args = array())
    {
        
    }
    

}