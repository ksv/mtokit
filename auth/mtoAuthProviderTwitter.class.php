<?php
mtoClass :: import("mtokit/auth/lib/twitteroauth/twitteroauth.php");
mtoClass :: import("mtokit/auth/lib/codebird/Codebird.class.php");

class mtoAuthProviderTwitter extends mtoAuthProviderAbstract
{
    
    protected $oauth_authorize = "https://api.twitter.com/oauth/authenticate";
    protected $oauth_access_token = "https://api.twitter.com/oauth/access_token";
    protected $oauth_request_token = "https://api.twitter.com/oauth/request_token";
    protected $oauth_authenticate = "https://api.twitter.com/oauth/authenticate";
    protected $name = "twitter";

    function __construct()
    {
        parent :: __construct();
    }

    public function authorize($hashback, $args = array())
    {
        $twitter = new TwitterOAuth($this->config['consumer_key'], $this->config['consumer_secret']);
        $request_token = $twitter->getRequestToken($this->confObj->get("core", "http_root") . $this->config['oauth_callback'] . '?hashback=' . $hashback);

        $token = $request_token['oauth_token'];
        $this->session->set('oauth_token', $token);
        $this->session->set('oauth_token_secret', $request_token['oauth_token_secret']);
         
        if (200 == $twitter->http_code)
        {
            header('Location: ' . $twitter->getAuthorizeURL($token));
        }
        else 
        {
            throw new mtoAuthException('An error occured while trying to fetch access token');
        }
    }

    public function fetchToken($args = array())
    {
        if ( $this->request->has("oauth_token") && $this->session->get("oauth_token") !== $this->request->get("oauth_token"))
        {
            session_destroy();
            header('Location: /auth/twitter');
            return;
        }

        $twitter = new TwitterOAuth($this->config['consumer_key'], $this->config['consumer_secret'], $this->session->get("oauth_token"), $this->session->get("oauth_token_secret"));
        $data = $twitter->getAccessToken($this->request->get("oauth_verifier"));

        $this->session->set("access_token", $data);
        $user_id = $data['user_id'];
        $user_name = $data['screen_name'];

        $this->session->delete("oauth_token");
        $this->session->delete("oauth_token_secret");
        $this->session->delete("access_token");

        if (200 == $twitter->http_code) 
        {
            return array(
                'token' => $data['oauth_token'],
                'user' => $data['user_id'],
                'email' => null
            );
        } 
        else 
        {
            return false;
        } 
    }

    public function checkToken($token, $id)
    {
        //
    }
    
    function refreshToken($token, $args = array())
    {
        
    }
    
    function post($uri, $title, $text, $args = array())
    {
        
    }
    
    function groupPost($uri, $title, $text, $args = array())
    {
        Codebird :: setConsumerKey($this->config['consumer_key'], $this->config['consumer_secret']);
        $t = Codebird :: getInstance();
        $t->setToken($this->config['group_token'], $this->config['group_token_secret']);
        if (mb_strlen($title, 'UTF-8') > 110)
        {
            $title = mb_substr($title, 0, 110, 'UTF-8');
        }
        $msg = $title . " " . $uri;
        $result = $t->statuses_update("status=" . $msg);
        if ($result->httpstatus != 200)
        {
            mtoProfiler :: instance()->logDebug(print_r($result, true), "social_group_error");
            throw new mtoException("social error");
        }
        mtoProfiler :: instance()->logDebug(print_r($result, true), "social_group_success");
        return $result;
    }
    


}