<?php

lmb_require("mtokit/help/mtoHelpNode.class.php");

class mtoHelpFileNode extends mtoHelpNode
{

    protected $heading = "h3";

    function generateContent()
    {
//      $content = array();
//      foreach ($this->getChildren() as $child)
//      {
//          $content = array_merge($content, $child->generateContent());
//      }
//      return $content;
        return $this->getContent();
    }

    function parse()
    {
        //$node = HelpNode :: create("bbcode");
        //$node->setContent(file_get_contents($this->getFilename()));
        //$node->parse();
        //$this->addChild($node);
        $this->setContent(file_get_contents($this->getFilename()));
    }

}
