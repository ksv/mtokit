<?php
mtoClass :: import("mtokit/webapp/mtoRoute.class.php");
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
mtoClass :: import("mtokit/core/traits/mtoFacade.trait.php");

class mtoApplication implements ArrayAccess
{
    use mtoSingletone;
    use mtoFacade;

    protected $request;
    protected $conf;
    protected $toolkit;
    protected $db = null;
    protected $route = null;
    protected $session;
    protected $response;
    protected $mode;

    protected $instances = array();
    protected $event_handlers = array();


    
    function __construct($root = null)
    {
        if (!is_null(static :: $instance))
        {
            throw new mtoException("Only one instance of mtoApplication allowed");
        }
        $this->mode = php_sapi_name() == "cli" ? "cli" : "web";

        $this->conf = mtoConf :: instance();
        if ($root)
        {
            $this->conf->set("core.root", $root);
            chdir($root);
        }
        static :: $instance = $this;
        $this->initEvents();
    }


    function config($conf)
    {
        $this->conf->loadConfig($conf);
        return $this;
    }
    

    
    function bootstrap($args = array())
    {
        $root = $this->conf->get("core.root");
        if (empty($root))
        {
            throw new mtoException("Application root not defined");
        }
        chdir($root);
        $this->conf->handleHttps();
    }
    

    function sessionStart()
    {
        $this->session->start();
    }
    
    protected function route($args = array())
    {
        $routes = $this->conf->get('webapp.route');
        if (is_array($routes))
        {
            foreach ($routes as $route)
            {
                $route_parts = explode("|", $route);
                $r = $route_parts[0];
                $d = array();
                if (isset($route_parts[1]))
                {
                    $def_parts = explode(";", $route_parts[1]);
                    for ($i=0; $i < count($def_parts); $i++)
                    {
                        if (strpos($def_parts[$i], "=") !== false)
                        {
                            list($k, $v) = explode("=", $def_parts[$i], 2);
                            $d[$k] = $v;
                        }
                    }
                }
                $m = isset($route_parts[2]) ? $route_parts[2] : "*";
                $this->route->add($m, $r, $d);
            }
        }
        $this->route->run($this->request);
        $this->route->apply($this->request);
        $this->route->map($this->request, array('controller' => 'mode'));
    }
    

    function createInstance($instance_str, $single = false)
    {
        $class = basename($instance_str);
        $path = $instance_str . ".class.php";
        if ($single && isset($this->instances[$class]))
        {
            return $this->instances[$class];
        }
        mtoClass :: import($path);
        $obj = new $class();
        if ($single)
        {
            $this->instances[$class] = $obj;
        }
        return $obj;
    }

    function bind($event, $handler, $modes = "*", $prepend = false)
    {
        if (!isset($this->event_handlers[$event]))
        {
            $this->event_handlers[$event] = array();
        }
        if (is_callable($handler))
        {
            $w = $handler;
        }
        elseif (is_string($handler) && strpos($handler, '@') !== false)
        {
            list($class, $method) = explode('@', $handler);
            $o = $this->createInstance($class, true);
            $w = array($o, $method);
        }
        else
        {
            throw new mtoException("Unknown class binging: " . $handler);
        }
        if ($prepend)
        {
            array_unshift($this->event_handlers[$event], array('handler' => $w, 'modes' => $modes));
        }
        else
        {
            array_push($this->event_handlers[$event], array('handler' => $w, 'modes' => $modes));
        }
    }

    function trigger($event, $data = array())
    {
        if (isset($this->event_handlers[$event]))
        {
            foreach ($this->event_handlers[$event] as $handler)
            {
                if (!$this->checkMode($handler['modes']))
                {
                    continue;
                }
                call_user_func($handler['handler'], $data);
            }
        }
    }

    function checkMode($modes)
    {
        if ($modes == '*')
        {
            return true;
        }
        $list = explode("|", $modes);
        $has_allow = false;
        foreach ($list as $m)
        {
            if (("!" . $this->mode) == $m)
            {
                return false;
            }
            if (strpos($m, '!') === false)
            {
                $has_allow = true;
            }
            if ($m == $this->mode)
            {
                return true;
            }
        }
        if ($has_allow)
        {
            return false;
        }
        return true;

    }
    
    function run($mode = null)
    {
        if (!is_null($mode))
        {
            $this->mode = $mode;
        }
        $this->trigger("app.bootstrap");
        $this->toolkit = mtoToolkit :: instance();
        $this->request = $this->toolkit->getRequest();
        $this->response = $this->toolkit->getResponse();
        $this->session = $this->toolkit->getSession();
        $this->db = $this->toolkit->getDbConnection();
        $this->route = new mtoRoute();
        try
        {
            $this->trigger("app.create_env");
            $this->trigger("app.session_start");
            $this->trigger("app.preroute");
            $this->trigger("app.route");
            $this->trigger("app.execute");
            $this->trigger("app.render");
            $this->trigger("app.shutdown");
        }
        catch (Exception $e) 
        {
            __D($e->getMessage());
            __D(debug_backtrace(), true);
            $this->trigger("app.exception", $e);
        }
    }

    function initEvents()
    {
        $this->bind("app.bootstrap", array($this, "bootstrap"));
        $this->bind("app.session_start", array($this, "sessionStart"), "!cli");
        $this->bind("app.route", array($this, "route"));
    }

    function getConf()
    {
        return $this->conf;
    }
    
    function getRequest()
    {
        return $this->request;
    }

    function getResponse()
    {
        return $this->response;
    }
    
    function getToolkit()
    {
        return $this->toolkit;
    }
    
    function getDb()
    {
        return $this->db;
    }
    
    function getSession()
    {
        return $this->session;
    }
    
    function getRoute()
    {
        return $this->route;
    }

    function getMode()
    {
        return $this->mode;
    }

    function offsetExists($offset)
    {
        return true;
    }

    function offsetGet($offset)
    {
        if (property_exists($this, $offset))
        {
            return $this->$offset;
        }
        else
        {
            return $this->conf->get($offset);
        }
    }

    function offsetSet($offset, $value)
    {
        $this->conf->set($offset, $value);
    }

    function offsetUnset($offset)
    {

    }
}