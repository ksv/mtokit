<?php
mtoClass :: import("mtokit/view/engines/mtoViewAbstractEngine.class.php");


class mtoViewMlteEngine extends mtoViewAbstractEngine
{
   
    function init() 
    {
        require_once __DIR__ . '/../lib/mlte/template.class.php';
        $this->tpl = new Template("templates");
    }
    
    
    function setBaseDir($dir)
    {
        $this->tpl->getLoader()->setPaths($dir);
    }
    
    function render($vars, $return = false)
    {        
        if (mtoConf :: instance()->env("wrap_to_twig"))
        {
            return $this->tpl->pparse("main_content", $return);
        }
        if (mtoConf :: instance()->env("mixed_engine_enabled"))
        {
            $this->tpl->assign_vars(array(
                'MAIN_CONTENT' => mtoConf :: instance()->env("mixed_engine")->render(true)
            ));
        }
        else
        {
            $this->tpl->assign_var_from_handle('MAIN_CONTENT', 'main_content');
        }
        return $this->tpl->pparse("page", $return);
    }
    
    function resolve($filename, $args = array())
    {
        $main_tpl = mtoConf :: instance()->env("main_template");
        $this->tpl->set_filenames(array('page' => $main_tpl));

        $this->tpl->set_filenames(array("main_content" => $filename));
        if (!empty($args['callback']))
        {
            $this->tpl->register_callback($args['callback']['instance'], $args['callback']['method']);
        }
        if (is_array($args['vars']))
        {
            $this->tpl->assign_vars($args['vars']);
        }
        if (is_array($args['block_vars']))
        {
            foreach ($args['block_vars'] as $block => $varset)
            {
                if (count($varset))
                {
                    foreach ($varset as $vars)
                    {
                        $this->tpl->assign_block_vars($block, $vars);
                    }
                }
                else
                {
                    $this->tpl->assign_block_vars($block, array());
                }
            }
        }
    }
    
    function set($name, $value = null)
    {
        if (is_array($name))
        {
            $this->tpl->assign_vars($name);
        }
        else
        {
            $this->tpl->assign_var($name, $value);
        }
    }
    
    function setBlock($block, $vars)
    {
        $this->tpl->assign_block_vars($block, $vars);
    }
    
}
  