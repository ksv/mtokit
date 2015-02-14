<?php
class mtoAuthProviderMailru extends mtoAuthProviderAbstract
{
    private $application_id;
    private $rest_api = "http://www.appsmail.ru/platform/api";
    protected $oauth_access_token = "https://connect.mail.ru/oauth/token";
    protected $oauth_authorize = "https://connect.mail.ru/oauth/authorize";
    protected $oauth_callback = "/auth/mailru_callback";
    protected $scope = "stream";
            
    protected $name = "mailru";

    public function __construct()
    {
        parent :: __construct();
        $this->application_id = $this->config['application_id'];
        $this->application_key = $this->config['consumer_key'];
        $this->application_secret = $this->config['consumer_secret'];
    }

    public function authorize($hashback, $args = array())
    {
        $args['http_hashback'] = $hashback;
        $info = array(
            'client_id' => $this->application_id,
            'response_type' => 'code',
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args),
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
            'client_id' => $this->application_id,
            'client_secret' => $this->application_secret,
            'grant_type' => "authorization_code",
            'code' => $this->request->get("code"),
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args)
        );

        $result = $this->http_call($this->oauth_access_token, $info, 'POST');
        $result = json_decode($result, true);

        if (isset($result['error']))
        {
            return false;
        }

        $user_profile = $this->usersGetInfo($result['access_token']);
        $user_profile = json_decode($user_profile, true);
        $email = isset($user_profile[0]['email']) ? $user_profile[0]['email'] : null;

        return array(
            'token' => $result['refresh_token'],
            'access_token' => $result['access_token'],
            'access_expired' => $result['expires_in'],
            'user' => $result['x_mailru_vid'],
            'email' => $email,
            'socid' => $user_profile[0]['uid'],
            'fname' => $user_profile[0]['first_name'],
            'lname' => $user_profile[0]['last_name']
        );
    }

    public function checkToken($token, $id)
    {
        //
    }
    
    function refreshToken($token, $args = array())
    {
        $info = array(
            'client_id' => $this->application_id,
            'client_secret' => $this->application_secret,
            'grant_type' => "refresh_token",
            'refresh_token' => $token
        );
        $result = json_decode($this->http_call($this->oauth_access_token, $info, 'POST'), true);
        return array('access_token' => $result['access_token'], 'access_expired' => $result['expires_in']);
    }
    
    function post($uri, $title, $text, $args = array())
    {
        $info = array(
            'method' => "stream.post",
            'app_id' => $this->application_id,
            'session_key' => $this->access_token,
            'format' => "json",
            'secure' => 1,
            'text' => $text,
            'title' => $title,
            'link1_text' => "default",
            'link1_href' => $uri,
            'link2_text' => "Прочитать",
            'link2_href' => $uri,
        );
        if (!empty($args['img']))
        {
            $info['img_url'] = $args['img'];
        }
        $info['sig'] = $this->sign($info);
        $result = json_decode($this->http_call($this->rest_api, $info), true);
        if (!empty($result['error']))
        {
            $this->log_error("stram.post", $info, $result);
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
    
    
    protected function usersGetInfo($session)
    {
        $args = array(
            'method' => 'users.getInfo',
            'app_id' => $this->application_id,
            'session_key' => $session,
            'secure' => 1,
            'format' => 'json'
        );

        $args['sig'] = $this->sign($args);

        $result = $this->http_call($this->rest_api, $args, 'GET');
        return $result;      
    }

    protected function sign($args = array())
    {
        $sign = '';
        ksort($args);

        foreach ($args as $key => $value) {
            $sign .= $key . '=' . $value;
        }

        return md5($sign . $this->application_secret);
    }
    
    
}