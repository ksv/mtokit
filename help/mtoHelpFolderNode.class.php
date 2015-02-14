<?php

lmb_require("mtokit/help/mtoHelpNode.class.php");

class mtoHelpFolderNode extends mtoHelpNode
{

    protected $heading = "h2";

    function parse()
    {
        $node = null;
        $files = lmbFs :: ls($this->getPath());
        foreach ($files as $file)
        {
            if (substr($file, 0, 1) == ".")
            {
                continue;
            }
            $parts = explode(".", $file);
            if ($parts[count($parts) - 1] != $this->extByType())
            {
                continue;
            }
            if ($file == ("info." . $this->extByType()))
            {
                $info = file_get_contents($this->getPath() . "/" . $file);
                if (preg_match("#\{method\:(.+)\}#simU", $info, $matches))
                {
                    list($path, $method) = explode(":", $matches[1]);
                    mtoClass :: import($path . ".class.php");
                    $parts = explode("/", $path);
                    $class = array_pop($parts);
                    $info = str_replace($matches[0], call_user_func_array(array($class, $method), array()), $info);
                }
                $this->setInfo($info);
            }
            elseif ($file == ("contents." . $this->extByType()))
            {
                //var_dump(2);
                $this->parseContents($this->getPath() . "/" . $file);
            }
            elseif (preg_match("#^cli_#", $file))
            {
                $node = mtoHelpNode :: create("cli");
                $node->setFilename($this->getPath() . "/" . $file);
            }
            elseif (preg_match("#^action_#", $file))
            {
                $node = mtoHelpNode :: create("controller");
                $node->setFilename($this->getPath() . "/" . $file);
            }
            elseif (preg_match("#^model_#", $file))
            {
                $node = mtoHelpNode :: create("model");
                $node->setFilename($this->getPath() . "/" . $file);
            }
            else
            {
                continue;
            }
            if ($node instanceof mtoHelpNode)
            {
                $name = str_replace("action_", "", $file);
                $name = str_replace("model_", "", $name);
                $name = str_replace("cli_", "", $name);
                $name = str_replace("." . $this->extByType(), "", $name);
                $node->setName($name);
                $node->parse();
                $this->addChild($node);
                $node = null;
            }
        }
    }

}

