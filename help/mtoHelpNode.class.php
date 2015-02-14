<?php

lmb_require("mtokit/text/mtoBbcodeParser.class.php");

abstract class mtoHelpNode
{

    protected $children = array();
    protected $args = array();
    protected $sections = array();
    protected $parts = array();
    protected $contents = array();
    protected $type;
    protected $changelog = array();
    protected $heading = "h0";
    protected $is_top = false;

    function __construct($args = array())
    {
        $this->args = $args;
    }

    abstract function parse();

    function setType($type)
    {
        $this->type = $type;
    }

    function extByType()
    {
        switch ($this->type)
        {
            case "enduser":
                return "uhlp";
                break;
            case "developer";
                return "dhlp";
                break;
            case "flow":
            default:
                return "hlp";
                break;
        }
    }

    function generateContent()
    {
//        if ($this->getGenerated())
//        {
//            return;
//        }
        $sections = array();
        //if ($this->is_top)
        //{
         //   array_push($sections, array('id' => $key, 'title' => $value, 'info' => $this->getInfo(), 'data' => $this->getContent(), 'heading' => $this->is_top ? "h1" : $this->heading));
        //}
        foreach ($this->parts as $key => $value)
        {
            $node = $this->getChildByName($key);
            if ($node instanceof mtoHelpNode)
            {
                //if ($this->is_top)
                //{
                    array_push($sections, array('id' => $key, 'title' => $value, 'info' => $node->getInfo(), 'data' => $node->getContent(), 'heading' => $this->is_top ? "h1" : $this->heading));
                //}
                $c = $node->generateContent();
                //print_r($c);
                //$node->setGenerated(true);
                if (is_array($c))
                {
                    foreach ($c as $k => $v)
                    {
                        $c[$k]['id'] = $key;
                    }
                    //array_push($sections, array('id' => $key, 'title' => $value, 'info' => $node->getInfo(), 'data' => $node->getContent(), 'heading' => $this->heading));
                    $sections = array_merge($sections, $c);
                }
                else
                {
                    //array_push($sections, array('id' => $key, 'title' => $value, 'info' => "", 'data' => $c, 'heading' => $this->heading));
                }
                //$node->setGenerated(true);
            }
            else
            {
                switch ($key)
                {
                    case "changelog":
                        array_push($sections, array('id' => $key, 'title' => $value, 'info' => "", 'data' => $this->generateChangeLog(), 'heading' => $this->heading, 'display' => 'none'));
                        break;
                    default:
                        array_push($sections, array('id' => $key, 'title' => $value, 'info' => '', 'data' => 'TBD', 'heading' => $this->is_top ? "h1" : $this->heading));
                        break;
                }
            }
        }
        $this->setGenerated(true);
        return $sections;
    }

    protected function parseBbcode($code)
    {
        $parser = new mtoBbcodeParser($code);
        return $parser->get_html();
    }

    protected function parseTextFile()
    {
        $lines = file($this->getFilename());
        $i = 0;
        $length = count($lines);
        $node = null;
        while ($i < $length)
        {
            if (substr($lines[$i], 0, 2) == "- ")
            {
                if (!is_null($node))
                {
                    
                }
            }
            elseif (substr($lines[$i], 0, 2) == "* ")
            {
                
            }
            else
            {

            }
            if ($node instanceof mtoHelpNode)
            {
                
            }
            $i++;
        }
    }

    function addChild(mtoHelpNode $child)
    {
        $this->children[] = $child;
    }

    function getChildren()
    {
        return $this->children;
    }

    function getChildByName($name)
    {
        foreach ($this->children as $child)
        {
            if ($child->getName() == $name)
            {
                return $child;
            }
        }
    }

    /**
     *
     * @param string $node_name
     * @return HelpNode
     */
    static function create($node_name)
    {
        $class = "mto" . lmb_camel_case("help_" . $node_name . "_node");
        lmb_require("mtokit/help/" . $class . ".class.php");
        return new $class();
    }

    function setArgs(array $args)
    {
        $this->args = $args;
    }

    function getArgs()
    {
        return $this->args;
    }

    function setArg($name, $value)
    {
        $this->args[$name] = $value;
    }

    function getArg($name)
    {
        return isset($this->args[$name]) ? $this->args[$name] : null;
    }

    function __set($name, $value)
    {
        $this->setArg($name, $value);
    }

    function __get($name)
    {
        return $this->getArg($name);
    }

    function __call($method, $args)
    {
        if (substr($method, 0, 3) == "get")
        {
            $name = lmb_under_scores(preg_replace("#^get#", "", $method));
            return $this->getArg($name);
        }
        elseif (substr($method, 0, 3) == "set")
        {
            $name = lmb_under_scores(preg_replace("#^set#", "", $method));
            $this->setArg($name, $args[0]);
            return true;
        }
        else
        {
            throw new lmbException("error");
        }
    }

    function parseContents($filename)
    {
        $arr = file($filename);
        foreach ($arr as $line)
        {
            list($key, $value) = preg_split("#\s+#", $line, 2);
            $this->parts[$key] = $value;
            if ($this->is_top)
            {
                $this->contents[$key] = $value;
            }
        }
    }

    function generateChangeLog()
    {
        $html = "";
        $log = array();
        foreach ($this->changelog as $version => $fixes)
        {
            $html .= '<h4 style="padding-left: 20px;">'.$version.'</h4><ul style="padding-left: 60px; list-style-type: disc;">';
            foreach ($fixes as $fix)
            {
                $html .= "<li>".$fix."</li>\n";
            }
            $html .= "</ul>";
        }
        
        return $html;
    }

    function parseChangelog($filename)
    {
        $content = file_get_contents($filename);
        $parts = preg_split("#=+\s#", $content);
        $cur_ver = "";
        $version = array();
        foreach ($parts as $part)
        {
            $lines = explode("\n", $part);
            foreach ($lines as $line)
            {
                if (preg_match("#\d+\.\d+\.\d+#", $line))
                {
                    if (!empty($version) && !empty($cur_ver))
                    {
                        $this->changelog[$cur_ver] = $version;
                    }
                    $version = array();
                    $cur_ver = $line;
                }
                if (preg_match("#^\s?-+\s+#", $line))
                {
                    $line = preg_replace("#^\s?-+\s+#", "", $line);
                    $version[] = $line;
                }
            }
        }
        if (!empty($version) && !empty($cur_ver))
        {
            $this->changelog[$cur_ver] = $version;
        }
    }

}

