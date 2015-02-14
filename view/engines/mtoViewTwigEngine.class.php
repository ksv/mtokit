<?php
mtoClass :: import("mtokit/view/engines/mtoViewAbstractEngine.class.php");
mtoClass :: import("classes/helper/CatalogHelper.class.php");
mtoClass :: import("classes/model/Informer.class.php");

class mtoViewTwigEngine extends mtoViewAbstractEngine
{
   
    function init() 
    {
        require_once __DIR__ . '/../lib/Twig/Autoloader.php';
        Twig_Autoloader::register();
        $loader = new Twig_Loader_Filesystem(mtoToolkit::instance()->resolveTemplatesBaseDir());
        $args = array();
        $conf = mtoConf :: instance()->getSection("view_twig");
        if ($conf['compile_tpl'])
        {
            $args['cache'] = "var/twig";
        }
        if (!empty($conf['strict_mode']))
        {
            $args['strict_variables'] = true;
        }
            
        $twig = new Twig_Environment($loader, $args);
        $twig->addExtension(new Twig_Extension_Timer());
        if (!empty($conf['extension']))
        {
            foreach ($conf['extension'] as $ext)
            {
                mtoClass :: import($ext . ".class.php");
                $cls = basename($ext);
                $twig->addExtension(new $cls());
            }
        }

        $this->tpl = $twig;
    }
    
    
    function resolve($filename, $args = array())
    {
        $this->setTemplatePath($filename);
    }
    
    function set($name, $value = null)
    {
        
    }
    
    function setBlock($block, $vars)
    {
        
    }
    
    
    function setBaseDir($dir)
    {
        $this->tpl->getLoader()->setPaths($dir);
    }
    function render($vars, $return = false)
    {
        try
        {
            $html = $this->tpl->render($this->template_path,$vars);
        }
        catch (Exception $e)
        {
            __L($e->getMessage(), "debug/twig");
            throw $e;
        }
        if (mtoConf :: instance()->get("view_twig", "compile_html"))
        {
            mtoClass :: import("mtokit/text/mtoHtmlMinimizer.class.php");
            $minimizer = new mtoHtmlMinimizer();
            $html = $minimizer->compress($html);
        }
        if ($return)
        {
            return $html;
        }
        else
        {
            echo $html;
        }
    }
    
    
    
    
    function enableStrictVariables()
    {
        $this->tpl->enableStrictVariables();
    }
}
  