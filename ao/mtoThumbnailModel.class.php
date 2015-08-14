<?php
mtoClass :: import('mtokit/ao/mtoActiveObject.class.php');

abstract class mtoThumbnailModel extends mtoActiveObject
{
    protected $_imgcache;
    protected $_thumbnail_args = array(
        'type' => "image",
        'id' => 0,
        'w' => 480,
        'h' => 480,
        'path' => ""
    );
    protected $_ow = 480;
    protected $_oh = 480;
    protected $_type = "image";

    function __construct($id=0, $eager = false)
    {
        parent :: __construct($id, $eager);
        //$this->_imgcache = $this->_toolkit->getCache("image");        
        $this->initThumbnailArgs();
    }

    function getThumbnail()
    {
        return $this->getAnyThumbnail(array('path' => $this->getBasicPath()));
    }

    function getAnyThumbnail($args)
    {

        
        if (isset($args['source']))
        {            
            $args['path'] = mtoToolkit :: instance()->generateFilename($args['source_type'], $args['source'], isset($args['source_object']) ? $args['source_object'] : null, isset($args['source_args']) ? $args['source_args'] : array());
        }
        if (!isset($args['path']))
        {
            throw new pdException("Source image path not specified");
        }
        $this->override($args);
                
        $filename = $this->_imgcache->get($args['path'], $this->_thumbnail_args);
        if (!$filename)
        {
            $filename = $this->_imgcache->set($args['path'], null, $this->_thumbnail_args);
        }
        return $filename ? $filename : ("/" . Media :: STUB_PATH);

    }

    function drop()
    {
        $this->_imgcache->remove($this->_thumbnail_args);
    }

    function flush()
    {
        if ($this->_imgcache && is_object($this->_imgcache))
        {
            $this->_imgcache->flush($this->_thumbnail_args);
        }
    }

    function get($property, $default = null)
    {
        if (preg_match("#^thumbnail_(.+)(path|url)$#s", $property, $matches))
        {
            $args = explode("_", $matches[1]);
            $size = array_shift($args);
            $last = $matches[2];
            list($w, $h) = explode("x", $size);
            $this->override(array('w' => $w, 'h' => $h));
            while (array_shift($args))
            {
                
            }
            $method = "getThumbnail" . ucfirst($last);
            return $this->$method();
        }
        return parent :: get($property, $default);
    }

    function has($name)
    {
        if (strpos($name, "thumbnail_") === 0)
        {
            return true;
        }
        return parent :: has($name);
    }


    function getThumbnailUrl()
    {
        $fname = $this->getThumbnail();
        if (strpos($fname,'http://') === false)
        {
            $fname = mtoConf :: instance()->get("cache_args", "url") . $fname;
        }
        return $fname;

    }

    function getAnyThumbnailUrl($args)
    {
        $fname = $this->getAnyThumbnail($args);
        if (strpos($fname,'http://') === false)
        {
            $fname = mtoConf :: instance()->get("cache_args", "url") . $fname;
        }
        return $fname;
    }

    function getThumnailPath()
    {
        return mtoConf :: instance()->get("cache_args", "path") .$this->getThumbnail();
    }
    
    
    abstract protected function getOriginalPath();
    abstract protected function getBasicPath();
    abstract protected function initThumbnailArgs();

    public function override($args = array())
    {
        foreach ($args as $k => $v)
        {
            $this->_thumbnail_args[$k] = $v;
        }
        return $this;
    }

    function import($values, $clean = false)
    {
        parent :: import($values);
        if (isset($values[$this->_config['pk']]))
        {
            $this->_id = $values[$this->_config['pk']];
        }
        $this->_thumbnail_args['id'] = $this->_id;
    }

    function getOriginalArgs()
    {
        $args = $this->_thumbnail_args;
        $args['w'] = $this->_ow;
        $args['h'] = $this->_oh;
        $args['type'] = $this->_type;
        return $args;
    }
}