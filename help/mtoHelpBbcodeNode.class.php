<?php

lmb_require("mtokit/help/mtoHelpNode.class.php");

class mtoHelpBbcodeNode extends mtoHelpNode
{

    function generateContent()
    {
        return array('title' => "", 'info' => "", 'data' => $this->parseBbcode($this->getContent()));
    }

    function parse()
    {
        
    }

}
