<?php

lmb_require("mtokit/help/mtoHelpNode.class.php");

class mtoHelpRootNode extends mtoHelpNode
{

    protected $heading = "h1";
    protected $is_top = true;

    function parse()
    {
        $dirs = lmbFs :: ls($this->getPath());
        foreach ($dirs as $dir)
        {
            if (is_dir($this->getPath() . "/" . $dir) && substr($dir, 0, 1) != ".")
            {
                $node = mtoHelpNode :: create("folder");
                $node->setPath($this->getPath() . "/" . $dir);
                $node->setName($dir);
                $node->parse();
                $this->addChild($node);
            }
        }
        $this->parseContents($this->getPath() . "/contents." . $this->extByType());
        $this->parseChangelog($this->getPath() . "/ChangeLog");
        //var_dump($this->changelog);
    }

}

