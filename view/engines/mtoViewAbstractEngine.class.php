<?php
abstract class mtoViewAbstractEngine
{
    protected $tpl;    
    public $template_resolved = 0;
    protected $template_path;
    protected $toolkit;
    protected $config;
    
    
    function __construct()
    {
        $this->toolkit = mtoToolkit :: instance();
        $this->config = $this->toolkit->getViewConf();
        $this->init();
    }
    
    abstract function init();
    abstract function resolve($filename, $args = array());
    abstract function set($name, $value = null);
    abstract function setBlock($block, $vars);
    abstract function render($vars, $return = false);
    
    
    function resolveTemplate($ctrl,$action='')
    {
        if (!$this->template_resolved)
        {    
            if (empty($action))
            {
                $action = 'default';
            } 

            $this->template_path = $ctrl.'/'.$action.'.' . $this->getTemplateExtension();
            $this->template_resolved = 1;
        }    
    }        
    
    function getTemplateExtension()
    {
        $name = $this->getEngineName();
        foreach ($this->config as $key => $value)
        {
            if (strpos($key, "ext_") === 0)
            {
                if ($value == $name)
                {
                    return str_replace("ext_", "", $key);
                }
            }
        }
        return "unknown";
    }
    
    function getEngineName()
    {
        $s = get_class($this);
        $s = str_replace("mtoView", "", $s);
        $s = str_replace("Engine", "", $s);
        return strtolower($s);
    }
    
    
    function getTemplatePath()
    {
        return $this->template_path;
    }
    
    
    function setTemplatePath($path)
    {
        return $this->template_path = $path;
    }
    
    function getEngine()
    {
        return $this->tpl;
    }
    
    
}