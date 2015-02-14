<?php

final class mtoClass
{

    private static $tries = array();
    private static $classes = array();
    private static $instance = null;
    private static $lookups = array();

    private $classpath = array();

    public static function create()
    {
        return new self();
    }

    public static function import($filename)
    {
        if (isset(self :: $tries[$filename]))
        {
            return;
        }
        else
        {
            self :: $tries[$filename] = true;
        }

        $class = false;


        $file = basename($filename);
        $items = explode('.', $file);

        if (isset($items[1]))
        {
            if ($items[1] == 'class' || $items[1] == 'interface' || $items[1] == "trait")
            {
                $class = $items[0];
            }
        }

        if ($class)
        {
            self :: $classes[$class] = $filename;
            return;
        }

        try
        {
            $filename = self :: $instance->getPath($filename);
            $file_found = include_once $filename;
        }
        catch (mtoException $e)
        {
            if (strpos($e->getMessage(), "include_once($filename)") !== false)
            {
                $file_found = false;
            }
            else
            {
                throw $e;
            }
        }

        if (!$file_found)
        {
            throw new mtoFileNotFoundException("Could not include source file '$filename'");
        }
    }
    
    public static function append($class, $filename)
    {
        self :: $tries[$filename] = true;
        self :: $classes[$class] = $filename;
    }

    public static function loadFolder($folder)
    {
        $files = mtoFs :: ls($folder);
        foreach ($files as $file)
        {
            $class = str_replace(array(".class.php", ".interface.php"), "", $file);
            self :: append($class, $folder . "/" . $file);
        }
    }

    public static function lookup($folder, $pattern, $check_method="", $force = false)
    {

        if (isset(self :: $lookups[$folder]) && !$force)
        {
            return self :: $lookups[$folder];
        }
        self :: $lookups[$folder] = array();
        $pattern = str_replace("*", "(.+)", $pattern);
        $files = mtoFs :: ls($folder);
        foreach ($files as $file)
        {
            if (is_file($folder . "/" . $file) && stripos($file, "abstract") === false)
            {
                if (preg_match("#" . $pattern . "\.class\.php#i", $file, $matches))
                {
                    $objName = str_replace("(.+)", ucfirst($matches[1]), $pattern);
                    self :: import($folder . '/' . $file);
                    if (class_exists($objName, true))
                    {
                        if (!empty($check_method))
                        {
                            if (method_exists($objName, $check_method))
                                self :: $lookups[$folder][] = $objName;
                        }
                        else
                        {
                            self :: $lookups[$folder][] = $objName;
                        }
                    }
                }
            }
        }
        return self :: $lookups[$folder];

    }

    public static function getLocators()
    {
        return self :: $classes;
    }
    
    public static function setLocators($locators)
    {
        self :: $classes = $locators;
    }

    public static function initPsr()
    {
        require_once("tools/vendor/autoload.php");
    }

    private function __construct()
    {
        self :: $tries = array();
        self :: $classes = array();
        spl_autoload_register(array($this, "autoload"));
        self :: $instance = $this;
    }

    private function autoload($name)
    {
        if (isset(self :: $classes[$name]))
        {
            //mtoClass :: import("mtokit/profiler/mtoProfiler.class.php");
            $file_path = $this->getPath(self :: $classes[$name]);
            if (!include_once($file_path))
            {
                $message = "Could not include source file '$file_path'";
                if (class_exists('mtoException') && class_exists('mtoBacktrace'))
                {
                    $trace = new mtoBacktrace(10, 1);
                    throw new mtoException($message, array('trace' => $trace->toString()));
                }
                else
                {
                    throw new Exception($message);
                }
            }
        }
    }

    private function getPath($file_path)
    {
        $mtoroot = mtoConf :: instance()->get("core", "__mtopath__");
        $root = mtoConf :: instance()->get("core", "root");



        if (empty($this->classpath))
        {
            $raw = mtoConf :: instance()->get("core", "classpath");
            $parts = explode(";", $raw);
            foreach ($parts as $part)
            {
                if (!empty($part))
                {
                    list($prefix, $path) = explode("=", $part);
                    $this->classpath[] = array('prefix' => $prefix, 'path' => $path);
                }
            }
        }



        if (strpos($file_path, "mtokit") === 0)
        {
            $file_path = $mtoroot . "/" . $file_path;
        }
        else
        {
            foreach ($this->classpath as $path)
            {
                if (strpos($file_path, $path['prefix']) === 0)
                {
                    $file_path = $root . "/" . $path['path'] . "/" . $file_path;
                }
            }
        }
//        elseif (strpos($file_path, "classes") === 0)
//        {
//            $file_path = $root . "/" . $file_path;
//        }
        return $file_path;
    }

}

