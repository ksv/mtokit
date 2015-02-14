<?php
class mtoCommonFilenameGenerator
{
    protected $parts = array();
    protected $toolkit;
    protected $parent;


    function __construct($parent = null)
    {
        $this->toolkit = mtoToolkit :: instance();
        $this->parent = $parent;
    }

    function parse($args = array())
    {
        $params = array();
        $parts = explode("_", array_pop($args));
        $filename = array_pop($parts);
        if (count($parts))
        {
            $part = array_shift($parts);
            if (strpos($part, "z") !== false)
            {
                list($params['w'], $params['h']) = explode("z", $part);
            }
            elseif (is_numeric($part))
            {
                $params['id'] = $part;
            }
        }
        if (count($parts) && !isset($params['id']))
        {
            $params['id'] = array_shift($parts);
        }
        $params['path'] = $this->toolkit->getFilepath($this->parent->getScope(), $filename, $params['id']);
        
        return $params;
        
    }

    function extract_args($args)
    {
        return $args;
    }



    function create($args)
    {
        return $this->toolkit->createImageConvertor($args['path']);
    }

    function generate($scope, $args)
    {
        $args['scope'] = $scope;
        $this->parts = array();
        $this->createParts($args);
        return implode("/", $this->parts);
    }

    function delete($args)
    {
        return false;
    }

    protected function createParts($args = array())
    {
        $this->appendDir($args);
        if (isset($args['w']) && isset($args['h']))
        {
            $this->parts[] = $args['w'] . "z" . $args['h'] . "_" . $args['id'] . "_" . basename($args['path']);
        }
        else
        {
            $this->parts[] = $args['id'] . "_" . basename($args['path']);
        }

//        $info = pathinfo($args['path']);
//
//        $file_parts = array();
//        $file_parts[] = md5($args['path']);
//
//        $filename = implode("_", $file_parts);
//
//        if (!empty($info['extension']))
//        {
//            $filename .= "." . $info['extension'];
//        }
//        $this->parts[] = $filename;
        
    }

    protected function appendDir($args = array())
    {
        $level_1 = substr(md5($args['id']),0,2);
        if ($level_1 == 'ad')
        {
            $level_1 = 'bd';
        }

        $level_2 = substr(md5($args['id']),2,2);
        if ($level_2 == 'ad')
        {
            $level_2 = 'bd';
        }
        $parts = $this->toolkit->getCachePathParts($args['id'], 2);

        $this->parts[] = $args['scope'];
        $this->parts[] = array_shift($parts);
        $this->parts[] = array_shift($parts);
//        $this->parts[] = $level_1;
//        $this->parts[] = $level_2;
    }

}