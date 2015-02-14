<?php
class mtoXMLCreator
{

    private $tag;
    private $data;
    private $startCDATA = "";
    private $endCDATA = "";
    private $attributs = array();
    private $children = array();

    public function __construct($tag, $cdata = false)
    {
        $cdata ? $this->setCDATA() : null;
        $this->tag = $tag;
    }

    public function setCDATA()
    {
        $this->startCDATA = "<![CDATA[";
        $this->endCDATA = "]]>";
    }

    public function setAttribute($attrName, $attrValue)
    {

        $newAttribute = array($attrName => $attrValue);
        $this->attributs = array_merge($this->attributs, $newAttribute);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function addChild($element)
    {
        if ($element && $element instanceof mtoXMLCreator)
        {
            array_push($this->children, $element);
        }
    }

    protected function getAttributs()
    {
        $attributs = "";
        if (is_array($this->attributs))
        {
            foreach ($this->attributs as $key => $val)
            {
                $attributs .= " " . $key . "=\"" . $val . "\"";
            }
        }
        return $attributs;
    }

    protected function getChildren()
    {
        $children = "";
        foreach ($this->children as $key => $val)
        {
            $children .= $val->getXML();
        }
        return $children;
    }

    public function getXML()
    {
        if (!$this->tag)
        {
            return "";
        }
        $xml = "<" . $this->tag . $this->getAttributs() . ">";
        $xml .= $this->startCDATA;
        $xml .= $this->data;
        $xml .= $this->endCDATA;
        $xml .= $this->getChildren();
        $xml .= "</" . $this->tag . ">";
        return $xml;
    }

    public function __destruct()
    {
        unset($this->tag);
    }

}
