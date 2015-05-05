<?php
mtoClass :: import('mtokit/cache/connection/mtoCacheAbstractConnection.class.php');
mtoClass :: import('mtokit/dfs/mtoCdnManager.class.php');
mtoClass :: import('mtokit/profiler/mtoProfiler.class.php');

class mtoCacheImageConnection extends mtoCacheAbstractConnection
{
    protected $connection;
    protected $toolkit;
    protected $generator;
    protected $cdn = null;
    protected $scope = null;
    protected $clip = true;

    function __construct($args = array())
    {

        parent::__construct($args);
        $this->toolkit = mtoToolkit :: instance();
        if (isset($args['generator']))
        {
            $generator = $args['generator'];
        }
        else
        {
            $generator = "common";
        }
        $class = "mto" . mto_camel_case($generator) . "FilenameGenerator";
        mtoClass :: import("mtokit/cache/generator/" . $class . ".class.php");
        $this->generator = new $class($this);
        if (isset($args['cdn']))
        {
            $this->cdn = mtoCdnManager :: create($args['cdn']);
        }
        if (!isset($args['scope']))
        {
            throw new mtoException("Cache scope not defined");
        }
        $this->scope = $args['scope'];
        if (isset($args['clip']))
        {
            $this->clip = $args['clip'];
        }
    }

    function setClip($clip)
    {
        $this->clip = $clip;
    }

    function getType()
    {
        return 'image';
    }


    function __destruct()
    {
    }

    function resolveArgs($args)
    {
        return $this->generator->parse($args);
    }


    function get($key, $args = array())
    {
        $ct = microtime(true);

        $args['path'] = $key;
        $args['instance'] = $key;
        $args['clip'] = $this->clip;
        $args = $this->generator->extract_args($args);
        $filename =  $this->generateFilename($args);
        if (!is_null($this->cdn) && !empty($args['skey']))
        {
            if (isset($args['changed']) && $args['changed'] > 0 && $args['changed'] > (time() - $this->cdn->getOption("replication_lag")*60))
            {
                $host = $this->cdn->getHostById($this->cdn->getOption("my_id"));
            }
            else
            {
                $host = $this->cdn->getHost(null, $args['skey']);
            }
            $filename = mtoConf :: instance()->get("core", "protocol") . $host . $this->cdn->getOption("url") . "/" . $filename;
        }
        else
        {
            $filename = mtoConf :: instance()->get("cache_args", "generator_url", true) . "/" . $filename;
        }

        $cct = microtime(true);

        if (($cct - $ct) >2 && isset($_SERVER['HTTP_REQUEST']))
        {
            mtoProfiler::instance()->logDebug($cct ."\t".$_SERVER['HTTP_REQUEST'], "time_img_debug");
        }
        return $filename;
    }
    
    function set($key, $value, $args = array())
    {
        $tm = microtime(true);
        $args['path'] = $key;
        $filename = $this->generateFilename($args);
        if (isset($args['noregenerate']))
        {
            if (mtoFs :: safeExists(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename, "r"))
            {
                if (filesize(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename) > 0)
                {
                    if (isset($args['raw_imagick']))
                    {
                        //return new Imagick(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename);
                        return $this->toolkit->createImageConvertor(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename)->getResource();
                    }
                    elseif (isset($args['raw_image']))
                    {
                        //return $this->toolkit->createImageConvertor(new Imagick(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename));
                        return $this->toolkit->createImageConvertor(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename);
                    }
                    else
                    {
                        return $filename;
                    }
                }
            }
        }
        mtoFs :: mkdir(dirname(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename));
        
        try
        {
            if (isset($args['conv']))
            {
                $conv = $args['conv'];
            }
            else
            {
                $conv = $this->generator->create($args);
            }
            if (!($conv instanceof mtoAbstractImageConvertor))
            {
                mtoProfiler :: instance()->logDebug("NOCONV:" . $conv, "debug/rest");
                return false;
                //mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\thas conv:" . isset($args['conv']), "debug/imgcache_no_instance");
            }


            if (isset($args['w']) && isset($args['h']))
            {
                $conv->resize(array('width' => $args['w'], 'height' => $args['h'], 'clip' => $this->clip));
            }
            if (isset($args['raw_imagick']))
            {
                $res = $conv->getResource();
                if (strpos($filename, "spacer.gif") !== false)
                {
                    mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " --- " . $_SERVER['HTTP_REFERER'], "debug/spacer");
                }
                else
                {
                    $conv->save(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename);
                }
                return $res;
            }
            if (isset($args['raw_image']))
            {
                return $conv;
            }
            else
            {
                if (strpos($filename, "spacer.gif") !== false)
                {
                    mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . " --- " . $_SERVER['HTTP_REFERER'], "debug/spacer");
                }
                else
                {
                    $conv->save(mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename);
                }
            }
        }
        catch(Exception $e)
        {
            //var_dump($e->getMessage());
            //__D(debug_backtrace(), true);
            //mtoProfiler :: instance()->logDebug($e->getMessage() . _D(debug_backtrace(), true, true), "xxx");
            if (in_array($e->getMessage(), array("NOITEM", "DELETED", "NOMEDIA")))
            {
                $filename = $e->getMessage();
            }
            else
            {
                $msg = array();
                $msg[] = $_SERVER['REQUEST_URI'];
                $msg[] = "[" . round(microtime(true) - $tm, 2) . "]";
                $msg[] = isset($_SERVER['HTTP_REFERER']) ? "[" . $_SERVER['HTTP_REFERER'] . "]" : "[]";
                $msg[] = $e->getMessage();
                $msg[] = $filename;
                $msg[] = _D(debug_backtrace(), true, true, true);
                $msg[] = $e->getFile();
                $msg[] = $e->getLine();
                mtoProfiler :: instance()->logDebug(implode("\t", $msg), "debug/imggen");
                return false;
            }
        }
        return $filename;
    }



    function add($key, $value, $args = array())
    {
        return $this->set($key, $value, $args);
    }

    function delete ($key, $args = array())
    {
        $args['path'] = $key;
        $args['instance'] = $key;
        $args['type'] = $this->scope;
        $args = $this->generator->extract_args($args);
        if (empty($args['id']))
        {
            $args['id'] = 0;
        }
        mtoProfiler :: instance()->logDebug("Connection has called delete for " . ($args['id']), "cdn_delete");
        //var_dump($args);
        if (!is_null($this->cdn) && !empty($args['skey']))
        {
            if (mtoConf :: instance()->get("dfs", "is_master"))
            {
                $section = $this->cdn->getSection($args['skey']);
                mtoProfiler :: instance()->logDebug("Try to delete reomte [".$args['type']."]" . $args['id'] . "in sectio" . $section, "cdn_delete");
                $this->cdn->cache_delete($args, $section);
            }
        }
        mtoProfiler :: instance()->logDebug("Connection calles generator for id" . $args['id'], "cdn_delete");
        if (!$this->generator->delete($args))
        {
            $filename = $this->generateFilename($args);
            $path = mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename;
            if (file_exists($path) && is_file($path))
            {
                unlink($path);
            }
        }
        $req_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "none";
        $req_ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "none";
        mtoProfiler :: instance()->logDebug("FLUSH\t" . $req_url . "\t" . $req_ref, mtoToolkit :: instance()->getCacheLogFilename($args['type'], isset($args['id']) ? $args['id'] : "unknown"));
    }

    function flush($args = array())
    {        
        $args['path'] = "test.test";
        $args['clip'] = $this->clip;
        $filename = $this->generateFilename($args);
        $path = mtoConf :: instance()->getFile("cache_args", "path") . "/" . $filename;
        
        if (file_exists(dirname($path)))
        {
            mtoFs :: rm(dirname($path));
        }
    }

    function replace($key, $value, $args = array())
    {
        $this->set($key, $value, $args);
    }

    function status()
    {
        return false;
    }

    function increment($key, $args = array())
    {
        return false;
    }

    function getName()
    {
        return "IMAGE";
    }

    function generateFilename($args)
    {
        return $this->generator->generate($this->scope, $args);
    }

    private function extractArgs($args)
    {
        $str = "";
        foreach ($args as $k => $v)
        {
            if (!is_array($v) && !is_object($v))
            {
                $str .= $k . "=" . $v . ";";
            }
        }
        return $str;
    }

    function getScope()
    {
        return $this->scope;
    }
}

