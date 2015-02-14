<?php
mtoClass :: import("mtokit/view/engines/mtoViewAbstractEngine.class.php");

class mtoViewTwigStringEngine extends mtoViewAbstractEngine
{
    protected $tpl_string;
    
    function init() 
    {
        require_once __DIR__ . '/../lib/Twig/Autoloader.php';
        Twig_Autoloader::register();
        $loader = new Twig_Loader_String();
        $twig = new Twig_Environment($loader, array(
                    //'cache' => '/var/cache/tpl',
                     ));
        $twig->addFilter('ceil', new Twig_Filter_Function('ceil'));
        $this->tpl = $twig;
    }
    
    function render($vars)
    {
        
        return $this->tpl->render($this->tpl_string,$vars);
    }
    
    function setTemplateString($template_string)
    {
        $this->tpl_string = $template_string;
    }
    
   function resolveTemplate($ctrl,$action='')
    {
        if (!$this->template_resolved)
        {    
            if (empty($action))
            {
                $action = 'default';
            } 

            $this->template_path = $ctrl.'/'.$action.'.twig';
            $this->template_resolved = 1;
        }    
    }        
    
    
}
  