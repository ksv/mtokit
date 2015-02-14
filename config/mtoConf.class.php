<?php
require_once(__DIR__ . "/../core/traits/mtoSingletone.trait.php");
require_once(__DIR__ . "/../core/traits/mtoFacade.trait.php");

class mtoConf
{
    private $config = array();
    private $suffix = "";
    private $files = array();
    private $env = array();
    
    use mtoSingletone;

    function __construct($args = array())
    {
        $this->loadConfig(__DIR__ . '/../mtokit.ini');
        if (isset($args['filename']))
        {
            $this->loadConfig($args['filename']);
        }
    }

    function loadConfig($filename = null)
    {
        if (isset($this->files[$filename]))
        {
            return $this;
        }
        $root = $this->get("core", "root");
        if (file_exists($filename))
        {
            $config = parse_ini_file($filename, true);
        }
        elseif (!empty($root) && file_exists($this->get('core', "root") . "/" . $this->get("core", "confdir") . "/" . $filename))
        {
            $config = parse_ini_file($this->get('core', "root") . "/" . $this->get("core", "confdir") . "/" . $filename, true);
        }
        elseif (strpos($filename, "mtokit") === 0)
        {
            $config = parse_ini_file($this->get("core", "__mtopath__") . "/" . $filename, true);
        }
        elseif (!empty($root))
        {
            throw new mtoException("Config not found: " . $filename);
        }
        else
        {
            return;
        }
        $this->files[$filename] = true;
        foreach ($config as $section => $items)
        {
            if (!isset($this->config[$section]))
            {
                $this->config[$section] = array();
            }
            foreach ($items as $key => $value)
            {
                if ($section == "core" && $key == "suffix")
                {
                    $this->suffix = $value;
                }
                if (isset($this->config[$section][$key]) && is_array($this->config[$section][$key]) && is_array($value))
                {
                    $this->config[$section][$key] = array_merge($this->config[$section][$key], $value);
                }
                else
                {
                    $this->config[$section][$key] = $value;
                }
            }
        }
        return $this;
    }

    function getSection($section)
    {
        if (isset($this->config[$section . "_" . $this->suffix]))
        {
            return $this->config[$section . "_" . $this->suffix];
        }
        if (isset($this->config[$section]))
        {
            return $this->config[$section];
        }
        return array();
    }

    function setSection($section, array $data)
    {
        $this->config[$section] = $data;
    }

    function get($section, $param = null, $url = false)
    {
        if (is_null($param) && strpos($section, ".") !== false)
        {
            list($section, $param) = explode(".", $section);
        }
        if (is_null($param) && strpos($section, "|") !== false)
        {
            list($section, $param) = explode("|", $section);
        }
        if (isset($this->config[$section . "_" . $this->suffix][$param]))
        {
            $val = $this->config[$section . "_" . $this->suffix][$param];
        }
        elseif (isset($this->config[$section][$param]))
        {
            $val = $this->config[$section][$param];
        }
        else
        {
            $val = null;
        }
        if ($url)
        {
            if (!empty($this->config['core']['use_https']))
            {
                $val = preg_replace("#^http://#", "https://", $val);
            }
        }
        return $val;
    }
    
    function val($key)
    {
        if (strpos($key, "|") !== false)
        {
            list($section, $param) = explode("|", $key);
        }
        else if (strpos($key, ".") !== false)
        {
            list($section, $param) = explode(".", $key);
        }
        else
        {
            return null;
        }
        return $this->get($section, $param);
    }

    function set($section, $param, $value = null)
    {
        if (strpos($section, ".") !== false)
        {
            $value = $param;
            list($section, $param) = explode(".", $section);
        }
        if (strpos($section, "|") !== false)
        {
            $value = $param;
            list($section, $param) = explode("|", $section);
        }
        if (!isset($this->config[$section]))
        {
            $this->config[$section] = array();
        }
        $this->config[$section][$param] = $value;
    }



    function getFile($section, $param = null)
    {
        return $this->get("core.root") . "/" . $this->get($section, $param);
    }

    function getFilename($filename)
    {
        return $this->get("core.root") . "/" . $filename;
    }

    function handleHttps()
    {
        if (!empty($_SERVER['HTTPS']))
        {
            $this->set("core.use_https", 1);
            $this->set("core.protocol", "https://");
        }
        else
        {
            $this->set("core.protocol", "http://");
        }
    }

    function env($name = null, $value = null, $append = false)
    {
        if (is_null($name))
        {
            return $this->env;
        }
        if (!is_null($value))
        {
            if ($append)
            {
                if (!isset($this->env[$name]))
                {
                    $this->env[$name] = array();
                }
                if ($append === true)
                {
                    $this->env[$name][] = $value;
                }
                else
                {
                    $this->env[$name][$append] = $value;
                }
            }
            else
            {
                $this->env[$name] = $value;
            }
        }
        return isset($this->env[$name]) ? $this->env[$name] : null;
    }

    function hasEnv($name)
    {
        return isset($this->env[$name]) && !empty($this->env[$name]);
    }

    static function getEnv($name = null, $value = null, $append = null)
    {
        return self :: instance()->env($name, $value, $append);
    }


}

