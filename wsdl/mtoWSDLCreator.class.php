<?php

mtoClass :: import("mtokit/wsdl/mtoXMLCreator.class.php");
mtoClass :: import("mtokit/wsdl/mtoPHPParser.class.php");

class mtoWSDLCreator
{

    private $PHPParser;
    private $XMLCreator;
    private $xsd = array("string" => "string", "bool" => "boolean", "boolean" => "boolean",
        "int" => "integer", "integer" => "integer", "double" => "double", "float" => "float", "number" => "float",
        "datetime" => "datetime",
        "resource" => "anyType", "mixed" => "anyType", "unknown" => "anyType", "unknown_type" => "anyType", "anytype" => "anyType"
    );
    private $soapenc = array("array" => "soapenc:Array");
    private $typensDefined = array();
    private $typens = array();
    private $typeTypens = array();
    private $complexTypens = array();
    private $typensURLS = array();
    public $classesGeneralURL;
    private $WSDL;
    private $WSDLXML;
    private $classes = array();
    private $classesURLS = array();
    private $name;
    private $url;
    private $messages = array();
    private $portTypes = array();
    private $bindings = array();
    private $services = array();
    private $paramsNames = array();
    private $includeMethodsDocumentation = true;

    public function __construct($name, $url)
    {

        $name = str_replace(" ", "_", $name);

        $this->PHPParser = new mtoPHPParser();

        $this->WSDLXML = new mtoXMLCreator("definitions");
        $this->WSDLXML->setAttribute("name", $name);
        $this->WSDLXML->setAttribute("targetNamespace", "urn:" . $name);
        $this->WSDLXML->setAttribute("xmlns:typens", "urn:" . $name);
        $this->WSDLXML->setAttribute("xmlns:xsd", "http://www.w3.org/2001/XMLSchema");
        $this->WSDLXML->setAttribute("xmlns:soap", "http://schemas.xmlsoap.org/wsdl/soap/");
        $this->WSDLXML->setAttribute("xmlns:soapenc", "http://schemas.xmlsoap.org/soap/encoding/");
        $this->WSDLXML->setAttribute("xmlns:wsdl", "http://schemas.xmlsoap.org/wsdl/");
        $this->WSDLXML->setAttribute("xmlns", "http://schemas.xmlsoap.org/wsdl/");
        $this->name = $name;
        $this->url = $url;
    }

    public function includeMethodsDocumentation($include = true)
    {
        $this->includeMethodsDocumentation = (bool) $include;
    }

    public function addFile($file)
    {
        $this->PHPParser->addFile($file);
    }

    public function ignoreNone()
    {
        $this->PHPParser->ignoreNone();
    }

    public function ignorePublic($ignore = false)
    {
        $this->PHPParser->ignorePublic($ignore);
    }

    public function ignoreProtected($ignore = false)
    {
        $this->PHPParser->ignoreProtected($ignore);
    }

    public function ignorePrivate($ignore = false)
    {
        $this->PHPParser->ignorePrivate($ignore);
    }

    public function ignoreStatic($ignore = false)
    {
        $this->PHPParser->ignoreStatic($ignore);
    }

    public function ignoreClass($class)
    {
        $this->PHPParser->ignoreClass($class);
    }

    public function ignoreClasses($classes)
    {
        $this->PHPParser->ignoreClasses($classes);
    }

    public function ignoreMethod($method)
    {
        $this->PHPParser->ignoreMethod($method);
    }

    public function ignoreMethods($methods)
    {
        $this->PHPParser->ignoreMethods($methods);
    }

    public function setClassesGeneralURL($url)
    {
        $this->classesGeneralURL = $url;
    }

    public function addURLToClass($className, $url)
    {
        $this->classesURLS[$className] = $url;
    }

    public function addURLToTypens($type, $url)
    {
        $this->typensURLS[$type] = $url;
    }

    private function addtypens($type)
    {
        static $t = 0;
        if (isset($this->typensURLS[$type]))
        {
            $this->typens["typens" . $t] = $this->typensURLS[$type];
            $this->typeTypens[$type] = "typens" . $t;
            $t++;
        }
        elseif (array_key_exists($type, $this->classes))
        {
            $this->typensDefined[$type] = $type;
        }
        else
        {
            $foundClasses = $this->PHPParser->getFoundClasses();
            if (in_array($type, $foundClasses))
            {
                trigger_error("There are no methods defined for <b>" . $type . "</b>", E_USER_ERROR);
            }
            else
            {
                trigger_error("URL for type <b>" . $type . "</b> or method for class <b>" . $type . "</b> not defined", E_USER_ERROR);
            }
        }
    }

    private function addComplexTypes($type)
    {

        if (substr($type, -2) == "[]")
        { 
            $complexType = trim(substr($type, 0, -2));

            $xsdComplexType = new mtoXMLCreator("xsd:complexType");
            $xsdComplexType->setAttribute("name", "ArrayOf" . $complexType);
            $xsdComplexTypeContent = new mtoXMLCreator("xsd:complexContent");
            $xsdComplexTypeContentRestriction = new mtoXMLCreator("xsd:restriction");
            $xsdComplexTypeContentRestriction->setAttribute("base", "soapenc:Array");
            $xsdComplexTypeContentRestrictionAttr = new mtoXMLCreator("xsd:attribute");
            $xsdComplexTypeContentRestrictionAttr->setAttribute("ref", "soapenc:arrayType");

            if (isset($this->xsd[strtolower($complexType)]))
            {
                $arrayType = "xsd:" . $complexType;
            }
            elseif (isset($this->soapenc[$complexType]))
            {
                $arrayType = $this->soapenc[$complexType];
            }
            else
            {
                $arrayType = "urn:" . $complexType . "[]";
            }

            $xsdComplexTypeContentRestrictionAttr->setAttribute("arrayType", $arrayType);
            $xsdComplexTypeContentRestriction->addChild($xsdComplexTypeContentRestrictionAttr);
            $xsdComplexTypeContent->addChild($xsdComplexTypeContentRestriction);
            $xsdComplexType->addChild($xsdComplexTypeContent);

            $this->complexTypens[$type] = $xsdComplexType;
        }
    }

    private function createMessage($name, $returnType = false, $params = array())
    {

        $message = new mtoXMLCreator("message");
        $message->setAttribute("name", $name);
        if (is_array($params))
        {
            foreach ($params as $pname => $param)
            {

                if (isset($this->paramsNames[$pname]))
                {
                    $pname = $pname . ($this->paramsNames[$pname] + 1);
                }
                else
                {
                    $this->paramsNames[$pname] = 0;
                }

                $part = new mtoXMLCreator("part");
                $part->setAttribute("name", $pname);
                $type = isset($param["varType"]) ? $param["varType"] : "anyType";
                if (isset($this->xsd[strtolower($type)]))
                {
                    $type = "xsd:" . $this->xsd[strtolower($type)];
                }
                elseif (isset($this->soapenc[$type]))
                {
                    $type = $this->soapenc[$type];
                }
                elseif (substr($type, -2) == "[]")
                {
                    $this->addComplexTypes($type);
                    $type = "urn:ArrayOf" . trim(substr($type, 0, -2));
                }
                else
                {
                    if (isset($this->typeTypens[$type]))
                    {
                        $type = $this->typeTypens[$type] . ":" . $type;
                    }
                    else
                    {
                        $this->addtypens($type);
                        $typens = isset($this->typensDefined[$type]) ? "typens" : $this->typeTypens[$type];
                        $type = $typens . ":" . $type;
                    }
                }
                $part->setAttribute("type", $type);
                $message->addChild($part);
            }
        }
        $this->messages[] = $message;

        if ($returnType)
        {
            $message = new mtoXMLCreator("message");
            $message->setAttribute("name", $name . "Response");
            $part = new mtoXMLCreator("part");
            $part->setAttribute("name", $name . "Return");
            $type = isset($returnType) ? $returnType : "anyType";
            if (isset($this->xsd[strtolower($type)]))
            {
                $type = "xsd:" . $this->xsd[strtolower($type)];
            }
            else
            {
                if (isset($this->typeTypens[$type]))
                {
                    $type = $this->typeTypens[$type] . ":" . $type;
                }
                elseif (isset($this->soapenc[$type]))
                {
                    $type = $this->soapenc[$type];
                }
                elseif (substr($type, -2) == "[]")
                {
                    $this->addComplexTypes($type);
                    $type = "urn:ArrayOf" . trim(substr($type, 0, -2));
                }
                else
                {
                    $this->addtypens($type);
                    $typens = isset($this->typensDefined[$type]) ? "typens" : $this->typeTypens[$type];
                    $type = $typens . ":" . $type;
                }
            }
            $part->setAttribute("type", $type);
            $message->addChild($part);
            $this->messages[] = $message;
        }
        else
        {
            $message = new mtoXMLCreator("message");
            $message->setAttribute("name", $name . "Response");
            $this->messages[] = $message;
        }
    }

    private function createPortType($portTypes)
    {
        if (is_array($portTypes))
        {
            foreach ($portTypes as $class => $methods)
            {
                $pt = new mtoXMLCreator("portType");
                $pt->setAttribute("name", $class . "PortType");
                foreach ($methods as $method => $components)
                {
                    $op = new mtoXMLCreator("operation");
                    $op->setAttribute("name", $method);
                    if ($this->includeMethodsDocumentation && $components["documentation"])
                    {
                        $doc = new mtoXMLCreator("documentation");
                        $doc->setData(trim($components["documentation"]));
                        $op->addChild($doc);
                    }
                    $input = new mtoXMLCreator("input");
                    $input->setAttribute("message", "typens:" . $method);
                    $op->addChild($input);

                    $output = new mtoXMLCreator("output");
                    $output->setAttribute("message", "typens:" . $method . "Response");
                    $op->addChild($output);

                    $pt->addChild($op);
                }
                $this->portTypes[] = $pt;
            }
        }
    }

    private function createBinding($bindings)
    {
        if (is_array($bindings))
        {
            $b = new mtoXMLCreator("binding");
            foreach ($bindings as $class => $methods)
            {
                $b->setAttribute("name", $class . "Binding");
                $b->setAttribute("type", "typens:" . $class . "PortType");
                $s = new mtoXMLCreator("soap:binding");
                $s->setAttribute("style", "rpc");
                $s->setAttribute("transport", "http://schemas.xmlsoap.org/soap/http");
                $b->addChild($s);
                foreach ($methods as $method => $components)
                {
                    $op = new mtoXMLCreator("operation");
                    $op->setAttribute("name", $method);
                    $s = new mtoXMLCreator("soap:operation");
                    $s->setAttribute("soapAction", "urn:" . $class . "Action");
                    $op->addChild($s);

                    $input = new mtoXMLCreator("input");
                    $s = new mtoXMLCreator("soap:body");
                    $s->setAttribute("namespace", "urn:" . $this->name);
                    $s->setAttribute("use", "encoded");
                    $s->setAttribute("encodingStyle", "http://schemas.xmlsoap.org/soap/encoding/");
                    $input->addChild($s);
                    $op->addChild($input);

                    $output = new mtoXMLCreator("output");
                    $output->addChild($s);
                    $op->addChild($output);
                    $b->addChild($op);
                }
                $this->bindings[] = $b;
            }
        }
    }

    private function createService($services)
    {
        if (is_array($services))
        {
            foreach ($services as $class => $methods)
            {
                if (isset($this->classesURLS[$class]) || $this->classesGeneralURL)
                {
                    $url = isset($this->classesURLS[$class]) ? $this->classesURLS[$class] : $this->classesGeneralURL;
                    $port = new mtoXMLCreator("port");
                    $port->setAttribute("name", $class . "Port");
                    $port->setAttribute("binding", "typens:" . $class . "Binding");
                    $soap = new mtoXMLCreator("soap:address");
                    isset($this->classesURLS[$class]) ? $soap->setAttribute("location", $this->classesURLS[$class]) : "";
                    $port->addChild($soap);
                }
                else
                {
                    trigger_error("URL for class <b>" . $class . "</b> was not defined", E_USER_ERROR);
                }
            }
            if (isset($port))
            {
                $this->services[] = $port;
            }
        }
    }

    public function createWSDL()
    {
        $this->classes = $this->PHPParser->getClasses();
        foreach ($this->classes as $class => $methods)
        {
            $pbs = array();
            ksort($methods);
            foreach ($methods as $method => $components)
            {
                if ($components["type"] == "public" || $components["type"] == "")
                {
                    if (array_key_exists("params", $components))
                    {
                        $this->createMessage($method, $components["returnType"], $components["params"]);
                    }
                    else
                    {
                        $this->createMessage($method, $components["returnType"]);
                    }
                    $pbs[$class][$method]["documentation"] = $components["description"];
                    $pbs[$class][$method]["input"] = $method;
                    $pbs[$class][$method]["output"] = $method;
                }
            }
            $this->createPortType($pbs);
            $this->createBinding($pbs);
            $this->createService($pbs);
        }

        foreach ($this->typens as $typenNo => $url)
        {
            $this->WSDLXML->setAttribute("xmlns:" . $typenNo, $url);
        }

        if (is_array($this->typensDefined) && count($this->typensDefined) > 0)
        {
            $types = new mtoXMLCreator("types");
            $xsdSchema = new mtoXMLCreator("xsd:schema");
            $xsdSchema->setAttribute("xmlns", "http://www.w3.org/2001/XMLSchema");
            $xsdSchema->setAttribute("targetNamespace", "urn:" . $this->name);
            $vars = $this->PHPParser->getClassesVars();
            foreach ($this->typensDefined as $typensDefined)
            {
                $complexType = new mtoXMLCreator("xsd:complexType");
                $complexType->setAttribute("name", $typensDefined);
                $all = new mtoXMLCreator("xsd:all");
                if (isset($vars[$typensDefined]) && is_array($vars[$typensDefined]))
                {
                    ksort($vars[$typensDefined]);
                    foreach ($vars[$typensDefined] as $varName => $varType)
                    {
                        $element = new mtoXMLCreator("xsd:element");
                        $element->setAttribute("name", $varName);
                        $varType = isset($this->xsd[$varType]) ? "xsd:" . $this->xsd[strtolower($varType)] : "anyType";
                        $element->setAttribute("type", $varType);
                        $all->addChild($element);
                    }
                }
                $complexType->addChild($all);
                $xsdSchema->addChild($complexType);
                foreach ($this->complexTypens as $ct)
                {
                    $xsdSchema->addChild($ct);
                }
            }
            $types->addChild($xsdSchema);
            $this->WSDLXML->addChild($types);
        }

        foreach ($this->messages as $message)
        {
            $this->WSDLXML->addChild($message);
        }

        foreach ($this->portTypes as $portType)
        {
            $this->WSDLXML->addChild($portType);
        }

        foreach ($this->bindings as $binding)
        {
            $this->WSDLXML->addChild($binding);
        }

        $s = new mtoXMLCreator("service");
        $s->setAttribute("name", $this->name . "Service");
        foreach ($this->services as $service)
        {
            $s->addChild($service);
        }
        $this->WSDLXML->addChild($s);

        $this->WSDL = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $this->WSDL .= $this->WSDLXML->getXML();

    }

    public function getWSDL()
    {
        return $this->WSDL;
    }

    public function printWSDL($headers = false)
    {
        if ($headers === true)
        {
            header("Content-Type: application/xml");
            print $this->WSDL;
            exit;
        }
        else
        {
            print $this->WSDL;
        }
    }

    public function saveWSDL($targetFile, $overwrite = true)
    {

        if (file_exists($targetFile) && $overwrite == false)
        {
            $this->downloadWSDL();
        }
        elseif ($targetFile)
        {
            $fh = fopen($targetFile, "w+");
            fwrite($fh, $this->getWSDL());
            fclose($fh);
        }
    }

    public function downloadWSDL()
    {
        session_cache_limiter();
        header("Content-Type: application/force-download");
        header("Content-Disposition: attachment; filename=" . $this->name . ".wsdl");
        header("Accept-Ranges: bytes");
        header("Content-Length: " . strlen($this->WSDL));
        $this->printWSDL();
        die();
    }

}

