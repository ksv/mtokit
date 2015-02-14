<?php
mtoClass :: import("mtokit/auth/lib/lightopenid/openid.php");
class mtoAuthProviderOpenid extends mtoAuthProviderAbstract
{
    private $_openid;

    public function __construct()
    {
        parent :: __construct();
        $this->_openid = new LightOpenID(mtoConf :: instance()->val("core|domain"));
    }

    public function authorize($hashback, $args = array())
    {
        if (!$this->_openid->mode && $this->request->has("realm"))
        {
            $this->_openid->identity = $this->confObj->get("auth_openid_providers", $this->request->get("realm"));
            $this->_openid->returnUrl = $this->confObj->get("auth_openid", "callback") . "?hashback=" . $hashback;

            if (strstr($this->_openid->identity, '%')) 
            {
                if ($this->request->has("user"))
                {
                    throw new mtoAuthException('Username for openid provider is undefined');
                } 
                
                $this->_openid->identity = str_replace('%', $this->request->get("user"), $openid->identity);
            }

            $this->_openid->required = array('contact/email');
            $this->_openid->optional = array('namePerson', 'namePerson/friendly');

            header('Location: ' . $this->_openid->authUrl());
        }
        else
        {
            throw new mtoAuthException("Invalid request");            
        }
    }

    public function fetchToken($args = array())
    {
        if ($this->_openid->mode == 'cancel')
        {
            return false;
        }
        
        if ($this->_openid->validate()) 
        {
            $profile = $this->_openid->getAttributes();
            $email = isset($profile['contact/email']) ? $profile['contact/email'] : null;
            $user = isset($profile['namePerson/friendly']) ? $profile['namePerson/friendly'] : null;

            return array(
                'token' => null,
                'user' => $user,
                'email' => $email
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
        
    }
    
}