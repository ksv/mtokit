<?php
mtoClass :: import("mtokit/auth/lib/facebook/facebook.php");

class mtoAuthProviderFb extends mtoAuthProviderAbstract
{
    private $fb;
    private $user;
    
    protected $name = "fb";
    protected $oauth_access_token = "";
    protected $oauth_authorize = "https://www.facebook.com/dialog/oauth";
    protected $scope = "email,publish_stream";

    public function __construct()
    {
        parent :: __construct();

        $this->application_key = $this->config['application_id'];
        $this->application_secret = $this->config['application_secret'];

        $this->fb = new Facebook(array(
          'appId'  => $this->application_key,
          'secret' => $this->application_secret,
        ));

        $this->user = $this->fb->getUser();
    }

    public function authorize($hashback, $args = array())
    {
        $args['http_hashback'] = $hashback;
        $info = array(
            'scope' => $this->scope,
            'redirect_uri' => $this->get_callback_uri($this->oauth_callback, $args),
        );

        $this->session->set('auth_hashback', $hashback);
        header('Location: ' . $this->fb->getLoginUrl($info));
    }

    public function fetchToken($args = array())
    {
        if ($this->user)
        {
            try {
                $profile = $this->fb->api('/me');

                return array('token' =>  $this->fb->getAccessToken(),
                             'user' => $profile['name'],
                             'email' => $profile['email'],
                             'fname' => $profile['first_name'],
                             'socid' => $profile['id'],
                             'lname' => $profile['last_name']
                );

            } catch (FacebookApiException $e) {
                return false;
            }            
        } else {
            return false;
        }
    }

    public function checkToken($token, $id)
    {
        //
    }
    
    function refreshToken($token, $args = array())
    {
        return array('access_token' => $token, 'access_expired' => 0);
    }
    
    function post($uri, $title, $text, $args = array())
    {
        $info = array(
            //'message' => $title,
            'link' => $uri,
            'name' => $title,
            'caption' => 'medkrug.ru',
            'description' => strip_tags($text),
            'access_token' => $this->access_token
        );
        if (!empty($args['img']))
        {
            $info['picture'] = $args['img'];
        }
        try
        {
            $result = $this->fb->api('/me/feed', 'POST', $info);
        }
        catch (FacebookApiException $e)
        {
            $result = array('error' => $e->getMessage());
        }
        if (empty($result['id']))
        {
            $this->log_error("/me/feed/post", $info, $result);
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
        //$this->fb->setExtendedAccessToken();
        //var_dump($this->fb->getAccessToken());
        $this->fb->setAccessToken($this->config['user_token']);
//        $this->fb->setRawMode(true);
//        $r = $this->fb->api("/oauth/access_token", 'GET', array(
//            'grant_type' => "client_credentials",
//            'scope' => "manage_pages,publish_stream",
//            'client_id' => $this->config['application_id'],
//            'client_secret' => $this->config['application_secret'],
//        ));
//        $this->fb->setRawMode(false);

//        var_dump($r);
        //var_dump($this->fb->getUser());
        
//        $r = $this->fb->api("/100001317383848/accounts", 'GET', array('access_token' => $this->config['user_token']));
//        var_dump($r);
//        die();
        //var_dump($r);
        //var_dump($this->fb->getAccessToken());
        //var_dump($this->fb->getUserAccessToken());
        //$t = $this->fb->api("/" . $this->config['page_id'] . "?fields=access_token", 'GET', array('fields' => "access_token", 'access_token' => $this->config['user_token']));
        //var_dump($t);
        //die();
        $info = array(
            'link' => $uri,
            'name' => $title,
            'description' => $text,
            'picture' => $args['image'],
            'access_token' => $this->config['page_token']
        );
        try
        {
            $result = $this->fb->api("/" . $this->config['page_id'] . "/feed", 'POST', $info);
        }
        catch (Exception $e)
        {
            $this->log_error("/" . $this->config['page_id'] . "/feed", $info, array());
            mtoProfiler :: instance()->logDebug(print_r($info, true) . "\n" . $e->getMessage(), "social_group_error");
            throw new mtoException($e->getMessage());
        }
        mtoProfiler :: instance()->logDebug(print_r($result, true), "social_group_success");
        
        //var_dump($result);
//        if (empty($t['access_token']))
//        {
//            mtoProfiler :: instance()->logDebug("no access token");
//        }
//        else
//        {
//            
//        }
    }
}