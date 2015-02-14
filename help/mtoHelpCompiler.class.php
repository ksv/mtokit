<?php

lmb_require("mtokit/help/mtoHelpNode.class.php");

class mtoHelpCompiler
{

    protected $path;
    protected $tree;
    protected $sections;
    protected $type;
    protected $html;

    function __construct($path)
    {
        $this->path = $path;
    }

    function create($path)
    {
        return new self($path);
    }

    function compile()
    {
        $this->tree = mtoHelpNode :: create("root");
        $this->tree->setPath($this->path);
        $this->tree->setType($this->type);
        $this->tree->parse();
        //print_r($this->tree);
        return $this;
    }

    function generateHTML()
    {
        $this->html = "";
        $sections = $this->tree->generateContent();
        //var_dump($sections);
        foreach ($sections as $section)
        {
            if (!isset($section['display']))
            {
                $section['display'] = "";
            }
            $parser = new mtoBbcodeParser($section['data']);
            $info_parser = new mtoBbcodeParser($section['info']);
            $this->html .= '<a href="#" rel="sect_link" ref="sect_'.$section['id'].'" onclick=""> <'.$section['heading'].'> '.$section['title'].' </'.$section['heading'].'> </a>' . "\n";
            $this->html .= '<div rel="sect_'.$section['id'].'" id="'.$section['id'].'_content" style="display: '.$section['display'].'">' . "\n";
            $this->html .= nl2br($info_parser->get_html());
            $this->html .= nl2br($parser->get_html());
            $this->html .= "</div>\n";
        }
        //$this->view = $view;
        //$this->view->set("sections", $this->tree->generateContent());
        return $this;
    }

    function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    function getContent()
    {
        return $this->html;
    }

    function generatePDF()
    {
        return $this;
    }

    function save($filename)
    {
        file_put_contents($filename, $this->html);
    }

}

