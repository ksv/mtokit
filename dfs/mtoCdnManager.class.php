<?php
/**
 */


mtoClass :: import("mtokit/soap/mtoSoapService.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnSoapService.class.php");

class mtoCdnManager
{
    private $config;
    private $skey;
    private static $instance = null;

    function __construct($skey)
    {
        if (empty($skey))
        {
            throw new mtoException("Segmentation key cant be empty");
        }
        $this->skey = $skey;
        $this->config = array('sections' => array(), 'locations' => array(), 'options' => mtoConf :: instance()->getSection("cdn_" . $skey), 'pool' => array(), 'ips' => array());
        $sections = mtoConf :: instance()->getSection("cdn_shards_" . $skey);
        foreach ($sections as $k => $section)
        {
            list($min, $max) = explode("|", $section);
            $this->config['sections'][$k] = array("min" => $min, "max" => $max);
        }
        $locations = mtoConf :: instance()->getSection("cdn_shard_distr_" . $skey);
        
        foreach ($locations as $k => $location)
        {
            $list = explode(",", $location);
            $this->config['locations'][$k] = $list;
        }
        $pool = mtoConf :: instance()->getSection("cdn_server_pool");
        foreach ($pool as $k => $server)
        {
            if (strpos($server, "|") !== false)
            {
                list($ihost, $chost, $comment, $ip) = explode("|", $server);
            }
            else
            {
                $ihost = $server;
                $chost = null;
                $comment = null;
                $ip = null;
            }
            $this->config['pool'][$k] = array('img' => $ihost, 'ctl' => $chost, 'comment' => $comment, 'ip' => $ip);
            if (!empty($ip))
            {
                $this->config['ips'][] = $ip;
            }
        }
    }
    
    function reload()
    {
        $this->__construct($this->skey);
    }

    static function create($skey = null)
    {
        if (is_null(self :: $instance))
        {
            self :: $instance = new self($skey);
        }
        return self :: $instance;
    }

    function getOption($name)
    {
        return isset($this->config['options'][$name]) ? $this->config['options'][$name] : null;
    }

    function isSectionLocked($section = null, $id = null)
    {
        if (is_null($section))
        {
            if (is_null($id))
            {
                throw new mtoException("Section not found");
            }
            $section = $this->getSection($id);
        }
        $locked = explode(",", $this->getOption("locked_sections"));
        return in_array($section, $locked);
    }

    function getSection($id)
    {
        foreach ($this->config['sections'] as $k => $section)
        {
            if ($id >= $section['min'] && $id <= $section['max'])
            {
                return $k;
            }
        }
        throw new mtoException("Section not found");
    }

    function getAllIps()
    {
        return $this->config['ips'];
    }
    
    function getAllHosts()
    {
        return $this->config['pool'];
    }
    
    function getSectionName($id)
    {
        return $skey . $this->getSection($skey, $id);
    }

    function getHostById($id, $param = "img")
    {
        if (isset($this->config['pool'][$id]))
        {
            return $this->config['pool'][$id][$param];
        }
        throw new mtoException("Host not found");
    }

    function getHostsBySection($section)
    {
         if (!isset($this->config['locations'][$section]))
         {
             throw new mtoException("Hosts not found");
         }
         return $this->config['locations'][$section];
    }

    function getHost($section = null, $id = null)
    {
        if (is_null($section))
        {
            if (is_null($id))
            {
                throw new mtoException("Host not found");
            }
            $section = $this->getSection($id);
        }
        
        if (!isset($this->config['locations'][$section]) || empty($this->config['locations'][$section]))
        {
            throw new mtoException("Host not found");
        }
        $host_id = $this->cooseHost($this->config['locations'][$section]);
        if ($this->isHostDown($host_id))
        {
            $host_id = 38;
        }
        return $this->getHostById($host_id);
    }


    function cooseHost($hostlist)
    {
        //this section should be used for load balancing
        shuffle($hostlist);
        return $hostlist[0];
    }

    function get($f, $section)
    {

        if (file_exists($f))
        {
            $this->log("EXISTS: " . $f, "cdn");
            return $f;
        }

        $hosts = $this->getHostsBySection($section);
        foreach ($hosts as $host_id)
        {
            if ($host_id != $this->getOption("my_id"))
            {
                $host = $this->getHostById($host_id, "ctl");
                $result  = mtoSoapService :: callService("http://" . $host . $this->getOption("wsdl_url"), "getFile", array(
                        'filename' => $f
                ));
                if (isset($result['content']))
                {
                    $filename = mtoConf :: instance()->get("core", "root") . "/" . $f;
                    if (file_exists($filename))
                    {
                        unlink($filename);
                    }
                    mtoFs :: mkdir(dirname($filename));
                    mtoFs :: safeWrite($filename, base64_decode($result['content']));
                    $this->log("GET: " . $f);
                    return $f;
                }
                
            }
        }
        $this->log("NOT FOUND: " . $f);
    }

    function cache_delete($args, $section)
    {
        $cdn_args = array();
        $cdn_args['id'] = $args['id'];
        $cdn_args['type'] = $args['type'];
        if (isset($args['skey']))
        {
            $cdn_args['skey'] = $args['skey'];
        }
        $hosts = $this->getHostsBySection($section);
        foreach ($hosts as $host_id)
        {
            //mtoProfiler :: instance()->logDebug("Manager: hosts for " . print_r($cdn_args, true) . " - " . print_r($hosts, true), "cdn_delete");
            if ($host_id != $this->getOption("my_id"))
            {
                $host = $this->getHostById($host_id, "ctl");
                mtoProfiler :: instance()->logDebug("Try remote flush on $host for " . $cdn_args['id'], "cdn_delete");
                $result = mtoSoapService :: callService("http://" . $host . $this->getOption("wsdl_url"), "deleteCache", $cdn_args);
            }
        }
        return array();
    }

    function getFile($args)
    {
        $filename = mtoConf :: instance()->get("core", "root") . "/" . $args['filename'];
        if (file_exists($filename))
        {
            $this->log("GET: " . $args['filename']);
            return array('content' => base64_encode(file_get_contents($filename)));
        }
        else
        {
            $this->log("NOT FOUND: " . $args['filename']);
            throw new mtoSoapException("File not found");
        }
    }

    function deleteCachedFile($args)
    {
        if ($args['type'] == "product")
        {
            $args['instance'] = new Product($args['id']);
        }
        else
        {
            $args['instance'] = null;
        }
        $cache = mtoToolkit :: instance()->getCache($args['type']);
        $cache->delete($args['instance'], $args);
        //unset($args['instance']);
        //mtoProfiler :: instance()->logDebug("I delete cache for " . print_r($args, true), "cdn_delete");
    }
    
    function isHostDown($host_id)
    {
        $cache = mtoToolkit :: instance()->getCache("memcache");
        $down_list = $cache->get("cdn_broken_hosts_" . mtoConf :: instance()->get("core", "suffix"));
        if (is_null($down_list))
        {
            if (file_exists("var/down.ini"))
            {
                $down_list = parse_ini_file("var/down.ini");
            }
            else
            {
                $down_list = array();
            }
            $cache->set("cdn_broken_hosts_" . mtoConf :: instance()->get("core", "suffix"), $down_list);
        }
        return in_array($host_id, array_keys($down_list));
    }
    
    function pushWsdl()
    {
        $pattern = $this->getOption('allowed_ip_pattern');
        if (!empty($pattern))
        {
            if (!preg_match($pattern, $_SERVER['REMOTE_ADDR']))
            {
                die("XML");
            }
        }
        header("Content-type: text/xml");
        echo mtoSoapService :: getWsdl("cdn.wsdl");
        die();
    }

    function handleSoapRequest()
    {
        ini_set("soap.wsdl_cache", 0);
        $server = new SoapServer(mtoConf :: instance()->getFile("soap", "wsdl_path") . '/cdn.wsdl');
        $server->setClass("mtoCdnSoapService");
        $server->handle();
    }

    function log($message, $file = "cdn")
    {
        if ($this->getOption('logging'))
        {
            if (isset($_SERVER['REQUEST_URI']))
            {
                $message .= "\t" . $_SERVER['REQUEST_URI'];
            }
            if (isset($_SERVER['HTTP_REFERER']))
            {
                $message .= "\t" . $_SERVER['HTTP_REFERER'];
            }
            mtoProfiler :: instance()->logError($message, $file);
        }
    }



}