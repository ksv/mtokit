<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');
mtoClass :: import('mtokit/view/mtoView.class.php');

class mtoViewTools extends mtoAbstractTools
{

    private $config;
    private $view = null;
    
    function __construct()
    {
        parent :: __construct();
        $this->config = mtoConf :: instance()->getSection("view");
    }
    
    
    function getView()
    {
        if (is_null($this->view))
        {
            $this->view = $this->createView();
        }
        return $this->view;
    }
    
    function getViewConf()
    {
        return $this->config;
    }

    function setView(mtoView $view)
    {
        $this->view = $view;
    }

    function hasView()
    {
        return ($this->view instanceof mtoView);
    }
    
    function createView()
    {
        return new mtoView();
    }

}