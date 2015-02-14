<?php
abstract class mtoUriGrabber
{
    protected $root = "";
    protected $base = "";
    protected $uri;
    protected $debug = false;
    protected $content = array();
    protected $transport = "native";
    protected $rules = array();
    protected $timeout = 1;
    protected $state_vars = array("base", "uri");
    protected $state_stack = array();
    protected $username = "";
    protected $password = "";
    protected $session_cookie_name = "PHPSESSID";
    protected $session_cookie = "";
    protected $logged = false;
    protected $last_resp_headers = array();
    protected $login_uri = "";
    protected $login_form = array();
    protected $login_method = "POST";
    
    
    
    function load($uri, $args = array())
    {
        if (empty($this->base))
        {
            $this->base = $this->root;
        }
        $this->uri = $this->normalizeUri($uri);
        $uri = $this->uri;
        if (strpos($uri, "#") !== false)
        {
            $parts = explode("#", $uri);
            $uri = $parts[0];
        }
        if (strpos($uri, "?") !== false)
        {
            $parts = explode("?", $uri);
            $uri = $parts[0];
        }
        $this->base = $uri;
        $method = "load_" . $this->transport;
        $this->content[$this->uri] = $this->$method($args);
        if ($this->debug)
        {
            var_dump("Loaded: " . $this->uri . "; Length: " . strlen($this->content[$this->uri]));
        }
        sleep($this->timeout);
        return $this;
    }
    
    function get()
    {
        return $this->content[$this->uri];
    }
    
    
    
    function getByRule($rule, $scope = "", $pop_single = true)
    {
        $content = !empty($scope) ? $scope : $this->content[$this->uri];
        if (is_array($this->rules[$rule]['rule']))
        {
            foreach ($this->rules[$rule]['rule'] as $regexp)
            {
                $check = $this->checkRule($regexp, $this->rules[$rule]['map'], $content, $pop_single);
                if (!empty($check))
                {
                    return $check;
                }
            }
            return array();
        }
        else
        {
            return $this->checkRule($this->rules[$rule]['rule'], $this->rules[$rule]['map'], $content, $pop_single);
        }
    }
    
    function checkRule($regexp, $map, $scope, $pop_single = true)
    {
        if (preg_match_all($regexp, $scope, $matches, PREG_SET_ORDER))
        {
            $result = array();
            foreach ($matches as $match)
            {
                $entry = array();
                foreach ($map as $k => $v)
                {
                    $entry[$v] = isset($match[$k]) ? $match[$k] : "";
                }
                $result[] = $entry;
            }
            if (count($result) == 1 && $pop_single)
            {
                return $result[0];
            }
            else
            {
                return $result;
            }
        }
        else
        {
            return array();
        }
    }
    
    
    
    function normalizeUri($uri)
    {
        $uri = trim($uri);
        if (strpos($uri, "http://") === 0)
        {
            return $uri;
        }
        if (strpos($uri, "//") === 0)
        {
            return preg_replace("#^//#", "http://", $uri);
        }
        if (strpos($uri, "https://") === 0)
        {
            return $uri;
        }
        if (strpos($uri, "./?") === 0)
        {
            return $this->base . substr($uri, 2);
        }
        if (strpos($uri, "./") === 0)
        {
            return $this->base . substr($uri, 1);
        }
        if (strpos($uri, "/") === 0)
        {
            return $this->root . $uri;
        }
        return $this->base . $uri;
    }
    
    
    protected function pushState()
    {
        $state = array();
        foreach ($this->state_vars as $var)
        {
            $state[$var] = $this->$var;
        }
        array_push($this->state_stack, $state);
    }
    
    protected function popState()
    {
        $state = array_pop($this->state_stack);
        foreach ($state as $key => $value)
        {
            $this->$key = $value;
        }
    }
    
    protected function login()
    {
        $this->load($this->root);
        foreach ($this->last_resp_headers as $header)
        {
            if (strpos($header, 'Set-Cookie: ') === 0)
            {
                $parts = explode(": ", $header, 2);
                $cparts = explode(";", $parts[1]);
                $cookie = explode("=", $cparts[0]);
                if ($cookie[0] == $this->session_cookie_name)
                {
                    $this->session_cookie = $cookie[1];
                }
            }
        }
        $form = $this->login_form;
        foreach ($form as $key => $value)
        {
            if (strpos($value, "__") === 0)
            {
                $prop = str_replace("__", "", $value);
                $form[$key] = $this->$prop;
            }
        }
        $args = array(
            'cookie' => $this->session_cookie_name . "=" . $this->session_cookie,
            'method' => $this->login_method,
            'content' => http_build_query($form),
            'follow_location' => 0
        );
        $this->load($this->login_uri, $args);
        foreach ($this->last_resp_headers as $header)
        {
            if (strpos($header, 'Set-Cookie: ') === 0)
            {
                $parts = explode(": ", $header, 2);
                $cparts = explode(";", $parts[1]);
                $cookie = explode("=", $cparts[0]);
                if ($cookie[0] == $this->session_cookie_name)
                {
                    $this->session_cookie = $cookie[1];
                }
            }
        }
        //var_dump($this->last_resp_headers);
        $this->logged = true;
    }
    
    
    private function load_native($args = array())
    {
        $context = null;
        if ($this->logged && $this->session_cookie)
        {
            $opts = array(
                'http' => array(
                    'header' => "Cookie: " . $this->session_cookie_name . "=" . $this->session_cookie . "\r\n"
                )
            );
            $context = stream_context_create($opts);
            //var_dump($opts);
        }
        if (!empty($args))
        {
            $opts = array('http' => array('header' => ""));
            if (!empty($args['cookie']))
            {
                $opts['http']['header'] .= "Cookie: " . $args['cookie'] . "\r\n";
            }
            if (!empty($args['method']))
            {
                $opts['http']['method'] = $args['method'];
                if ($args['method'] == "POST")
                {
                    $opts['http']['header'] .= "Content-type: application/x-www-form-urlencoded\r\n";
                    $opts['http']['header'] .= "Referer: " . $this->root . "\r\n";
                    $opts['http']['header'] .= "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.79 Safari/537.4 FirePHP/4Chrome\r\n";
                }
            }
            if (!empty($args['content']))
            {
                $opts['http']['content'] = $args['content'];
                $opts['http']['header'] .= "Content-Length: " . strlen($args['content']);
            }
            if (isset($args['follow_location']))
            {
                $opts['http']['follow_location'] = $args['follow_location'];
            }
            //var_dump($opts);
            $context = stream_context_create($opts);
        }
        $content = file_get_contents($this->uri, false, $context);
        $this->last_resp_headers = $http_response_header;
        //var_dump($this->last_resp_headers);
        return $content;
    }
    
}