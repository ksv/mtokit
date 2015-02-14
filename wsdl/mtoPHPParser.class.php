<?php

class mtoPHPParser
{

    private $files = array();
    private $ignoredClasses = array();
    private $ignoredMethods = array();
    private $ignorePublic = false;
    private $ignoreProtected = true;
    private $ignorePrivate = true;
    private $ignoreStatic = false;
    private $classes = array();
    private $foundClasses = array();
    private $classesVars = array();
    private $allData = array();
    private $currentClass;
    private $currentMethodComment;
    private $currentMethodType;
    private $currentMethod;
    private $currentParams = array();
    private $WSDL;
    private $WSDLMessages = array();
    private $bindings = array();
    private $portTypes = array();
    private $WSDLService = array();

    public function __construct()
    {

    }

    public function ignoreNone()
    {
        $this->ignoredClasses = array();
        $this->ignoredMethods = array();
        $this->ignorePrivate = array();
        $this->ignoreProtected = array();
        $this->ignorePublic = array();
        $this->ignoreStatic = array();
    }

    public function ignorePublic($ignore = false)
    {
        if ($ignore === true)
        {
            $this->ignorePublic = true;
        }
        elseif ($ignore === false)
        {
            $this->ignorePublic = false;
        }
    }

    public function ignoreProtected($ignore = false)
    {
        if ($ignore === true)
        {
            $this->ignoreProtected = true;
        }
        elseif ($ignore === false)
        {
            $this->ignoreProtected = false;
        }
    }

    public function ignorePrivate($ignore = false)
    {
        if ($ignore === true)
        {
            $this->ignorePrivate = true;
        }
        elseif ($ignore === false)
        {
            $this->ignorePrivate = false;
        }
    }

    public function ignoreStatic($ignore = false)
    {
        if ($ignore === true)
        {
            $this->ignoreStatic = true;
        }
        elseif ($ignore === false)
        {
            $this->ignoreStatic = false;
        }
    }

    public function ignoreClass($class)
    {
        $this->ignoredClasses[] = $class;
    }

    public function ignoreClasses($classes)
    {
        if (is_array($classes))
        {
            foreach ($classes as $class)
            {
                $this->ignoreClass($class);
            }
        }
    }

    public function ignoreMethod($method)
    {
        if (is_array($method))
        {
            $this->ignoredMethods[key($method)][] = $method[key($method)];
        }
    }

    public function ignoreMethods($methods)
    {
        if (is_array($methods))
        {
            foreach ($methods as $class => $method)
            {
                if ($class != "" && $method != "")
                    $this->ignoredMethods[$class][] = $method;
            }
        }
    }

    public function addFile($file)
    {
        if (file_exists($file))
        {
            $this->files[] = $file;
        }
        else
        {
            trigger_error("File <b>" . $file . "</b> does not exist !!", E_USER_ERROR);
        }
    }

    private function getNextToken()
    {
        if (is_array($this->allData))
        {
            while (($c = next($this->allData)))
            {
                if (!is_array($c) || $c[0] == T_WHITESPACE)
                { // 370
                    continue;
                }
                break;
            }
            return current($this->allData);
        }
        return false;
    }

    private function getPrevToken()
    {
        if (is_array($this->allData))
        {
            while (($c = prev($this->allData)))
            {
                if (!is_array($c) || $c[0] == T_WHITESPACE)
                { // 370
                    continue;
                }
                break;
            }
            return current($this->allData);
        }
        return false;
    }

    private function getNextTokenWithType($type)
    {
        while (($current = $this->getNextToken()))
        {
            if ($current[0] == $type)
            {
                return current($this->allData);
            }
        }
        return array();
    }

    private function parseFile()
    {
        $lookForClassVariables = true; 
        while (($token = $this->getNextToken()))
        {

            if ($token[0] == T_CLASS)
            { 
                $className = $this->getNextTokenWithType(T_STRING);
                $this->currentClass = $className[1];
                $this->currentMethodComment = $this->currentMethodType = $this->currentMethod = $this->currentParams = null;
                continue;
            }

            if ($lookForClassVariables === true && $token[0] == T_VARIABLE && $this->currentClass != null)
            {
                $varName = substr($token[1], 1);
                $this->classesVars[$this->currentClass][$varName] = "";
                continue;
            }

            if ($token[0] == T_DOC_COMMENT)
            { 
                $nt = $this->getNextToken();

                if ($nt[0] == T_FUNCTION || $nt[0] == T_STATIC || $nt[0] == T_ABSTRACT || $nt[0] == T_FINAL ||
                        $nt[0] == T_PRIVATE || $nt[0] == T_PROTECTED || $nt[0] == T_PUBLIC)
                { 
                    $nnt = $this->getNextToken();
                    if ($nnt[0] == T_VARIABLE)
                    {
                        $varName = substr($nnt[1], 1);
                        $this->getPrevToken();
                        $varType = $this->getPrevToken();
                        if ($varType[0] == T_DOC_COMMENT)
                        {
                            $varType = $this->parseComment($varType[1]);
                            $varType = $varType['params']['type'];
                            $this->classesVars[$this->currentClass][$varName] = $varType;
                        }
                        continue;
                    }
                    else
                    {
                        $this->getPrevToken();
                    }
                    $this->currentMethodComment = $token[1];
                    $this->currentMethod = null;
                    $this->currentParams = null;
                    $this->getPrevToken();
                    continue;
                }
            }

            if (isset($nt) && ($nt[0] == T_STATIC || $nt[0] == T_ABSTRACT || $nt[0] == T_FINAL ||
                    $nt[0] == T_PRIVATE || $nt[0] == T_PROTECTED || $nt[0] == T_PUBLIC))
            { 
                $this->currentMethodType = $token[1] ? $token[1] : "public";
                $this->currentMethod = $this->currentParams = null;
                $token = $this->getNextToken();
            }
            else
            {
                $this->currentMethodType = "public";
            }

            if ($token[0] == T_FUNCTION)
            { 
                $lookForClassVariables = false;
                $f = $this->getNextTokenWithType(T_STRING);
                $this->currentMethod = $f[1];
                $this->currentParams = null;
                if (next($this->allData) == "(")
                {
                    while (($p = next($this->allData)) != ")")
                    {
                        if ($p[0] == T_VARIABLE)
                        { 
                            $this->currentParams[] = $p[1];
                        }
                    }
                }
            }

            if (!isset($this->classes[$this->currentClass]))
            {
                $this->foundClasses[$this->currentClass] = $this->currentClass;
            }

            if ($this->currentClass && $this->currentMethod)
            {
                $this->classes[$this->currentClass][$this->currentMethod]["comment"] = $this->currentMethodComment;
                if ($this->currentMethod == null)
                    $this->currentMethod = "public";
                $this->classes[$this->currentClass][$this->currentMethod]["type"] = $this->currentMethodType;
                $this->classes[$this->currentClass][$this->currentMethod]["params"] = $this->currentParams;
                $this->currentMethodComment = $this->currentMethodType = $this->currentMethod = $this->currentParams = null;
            }
        }
    }

    private function filterClasses()
    {
        foreach ($this->classes as $class => $methods)
        {
            if (in_array($class, $this->ignoredClasses))
            {
                unset($this->classes[$class]);
                continue;
            }

            foreach ($methods as $method => $attrs)
            {

                if (($attrs["type"] == "public" && $this->ignorePublic === true) ||
                        ($attrs["type"] == "protected" && $this->ignoreProtected === true) ||
                        ($attrs["type"] == "private" && $this->ignorePrivate === true) ||
                        ($attrs["type"] == "static" && $this->ignoreStatic === true))
                {
                    unset($this->classes[$class][$method]);
                }

                if (isset($this->ignoredMethods[$class]) && is_array($this->ignoredMethods[$class]))
                {
                    if (in_array($method, $this->ignoredMethods[$class]))
                    {
                        unset($this->classes[$class][$method]);
                    }
                }
            }
        }
    }

    private function parseComment($comment)
    {
        $comment = trim($comment);
        if ($comment == "")
            return "";

        if (strpos($comment, "/*") === 0 && strripos($comment, "*/") === strlen($comment) - 2)
        {
            $lines = preg_split("(\\n\\r|\\r\\n\\|\\r|\\n)", $comment);
            $description = "";
            $returntype = "";
            $params = array();
            while (next($lines))
            {
                $line = trim(current($lines));
                $line = trim(substr($line, strpos($line, "* ") + 2));
                if (isset($line[0]) && $line[0] == "@")
                {
                    $parts = explode(" ", $line);
                    if ($parts[0] == "@return")
                    {
                        $returntype = $parts[1];
                    }
                    elseif ($parts[0] == "@param")
                    {
                        $params[$parts[2]] = $parts[1];
                    }
                    elseif ($parts[0] == "@var")
                    {
                        $params['type'] = $parts[1];
                    }
                }
                else
                {
                    $description .= "\n" . trim($line);
                }
            }

            $comment = array("description" => $description, "params" => $params, "return" => $returntype);
            return $comment;
        }
        else
        {
            return "";
        }
    }

    private function parseClasses()
    {
        $classes = $this->classes;
        $this->classes = array();
        foreach ($classes as $class => $methods)
        {
            foreach ($methods as $method => $attributes)
            {
                $this->classes[$class][$method]["type"] = $attributes["type"];
                $commentParsed = $this->parseComment($attributes["comment"]);
                $this->classes[$class][$method]["returnType"] = !isset($commentParsed["return"]) ? false : $commentParsed["return"];
                $this->classes[$class][$method]["description"] = isset($commentParsed["description"]) ? $commentParsed["description"] : "";
                if (is_array($attributes["params"]))
                {
                    foreach ($attributes["params"] as $param)
                    {
                        $paramName = substr($param, 1);
                        $this->classes[$class][$method]["params"][$paramName]["varName"] = $param;
                        if (isset($commentParsed["params"][$param]))
                            $this->classes[$class][$method]["params"][$paramName]["varType"] = $commentParsed["params"][$param];
                    }
                }
            }
        }
    }

    public function getClasses()
    {
        foreach ($this->files as $file)
        {
            $this->allData = token_get_all(file_get_contents($file));
            $this->parseFile(file_get_contents($file));
        }
        $this->filterClasses();
        $this->parseClasses();
        return $this->classes;
    }

    public function getFoundClasses()
    {
        return $this->foundClasses;
    }

    public function getClassesVars()
    {
        return $this->classesVars;
    }

}
