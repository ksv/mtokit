<?php
class mtoView
{
    
    protected $engine;
    protected $vars = array();
    protected $blocks = array();
    protected $is_rendered = false;
    protected $is_resolved = false;
    protected $callback;



    function __construct()
    {
//        mtoClass::import('mtokit/view/engines/mtoView'.mto_camel_case($engine).'Engine.class.php');
//        $class = 'mtoView'.mto_camel_case($engine).'Engine';
//        $this->engine = new $class;
    }
    
    function resolve($filename)
    {
        //_D(debug_backtrace(), true);
        if ($this->is_resolved)
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " :: resolved twice :: " . $filename, "debug/resolve");
            throw new mtoException("Template already resolved");
        }
        $parts = explode(".", $filename);
        $ext = array_pop($parts);
        $conf = mtoConf :: instance()->getSection("view");
        $engine = "";
        foreach ($conf as $key => $value)
        {
            if (strpos($key, "ext_") === 0)
            {
                if (str_replace("ext_", "", $key) == $ext)
                {
                    $engine = $value;
                }
            }
        }
        if (empty($engine))
        {
            $engine = $conf['default'];
        }
        $class = "mtoView" . mto_camel_case($engine) . "Engine";
        mtoClass :: import("mtokit/view/engines/".$class.".class.php");
        $this->engine = new $class();
        $this->engine->resolve($filename, array(
            'callback' => $this->callback,
            'vars' => $this->vars,
            'block_vars' => $this->blocks
        ));
        $this->is_resolved = true;
    }

    //deprecated
    function assign_vars($vars)
    {
        $this->vars = array_merge($this->vars,$vars);
        if ($this->is_resolved)
        {
            $this->engine->set($vars);
        }
    }
    
    //deprecated
    function assign_block_vars($block,$vars)
    {
        if (!isset($this->blocks[$block]))
        {
            $this->blocks[$block] = array();
        }
        $this->blocks[$block][] = $vars;
        if ($this->is_resolved)
        {
            $this->engine->setBlock($block, $vars);
        }
    }
    
    function getEngine()
    {
        return $this->engine;
    }
    
    function getNativeEngine()
    {
        //_D(debug_backtrace(), true);
        return $this->engine->getEngine();
    }
    
    
    function getEngineName()
    {
        $class = get_class($this->engine);
        $class = str_replace('mtoView', '', $class);
        $class = str_replace('Engine', '', $class);
        return mto_under_scores($class);
    }
    
    function set($name,$val)
    {
        $this->vars[$name] = $val;
    }        
    
    function render($return=false)
    {     
        if ($this->is_rendered)
        {
            return;
        }    
        if (!$this->is_resolved)
        {
            __L(array(print_r($_REQUEST, true), _D(debug_backtrace(), true, true, true)), "debug/resolve");
            throw new mtoException("Template is not resolved");
        }
        return $this->engine->render($this->vars, $return);
    }
    
    function getCurrentPath()
    {
        return $this->engine->getTemplatePath();
    }
    
    
    function setFilename($path)
    {
        $this->engine->template_resolved = 1;
        return $this->engine->setTemplatePath($path);
    }
    
    function loadTemplate($path)
    {
        $this->setFilename($path);
        return $this;
    }
    
    function resolveTemplate()
    {
        $mode = mtoConf :: instance()->env("current_mode");
        $action = mtoConf :: instance()->env("current_action");
        $this->engine->resolveTemplate($mode,$action);
        
        //return $this->setFilename($path);
    }
    
    function setBaseDir($dir)
    {
        $this->engine->setBaseDir($dir);
    }
    
    
    function isTemplateResolved()
    {
        //return $this->engine->template_resolved;
        return $this->is_resolved;
    }
    
    function setTemplateString($template_string)
    {
        
        $this->engine->setTemplateString($template_string);
        
    }
    
    function getBlocks()
    {
        return $this->blocks;
    }
    
    //shit
    function set_filenames()
    {}
    
    //shit
    function assign_var_from_handle()
    {}
    
    //shit
    function register_callback($object, $method)
    {
        $this->callback = array('instance' => $object, 'method' => $method);
    }
   
    //shit
    function pparse($eres)
    {
        return $this->render();
    }
    

    
                
                
    

}