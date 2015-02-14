<?php
mtoClass :: import("mtokit/cache/generator/mtoCommonFilenameGenerator.class.php");
mtoClass :: import("classes/model/PrintArea.class.php");

class mtoPrintAreaFilenameGenerator extends mtoCommonFilenameGenerator
{
    function parse($args = array())
    {
        $params = array();
        $property = array_shift($args);
        $l1 = array_shift($args);
        $l2 = array_shift($args);        
        $filename = array_shift($args);       
        $parts = explode("_", $filename);
        $params['id'] = array_shift($parts);
        list($params['w'], $params['h']) = explode("z", array_shift($parts));
        $params['clip'] = array_shift($parts);
        $params['property'] = $property;
        $params['instance'] = new PrintArea($params['id']);
        $params['path'] = $this->toolkit->getFilename("area_image", $params['instance']->get($params['property']), $params['id']);


        return $params;
    }

    function fetch($args = array())
    {
        if (!isset($args['id']))
        {
            return $args;
        }
        $args['instance'] = new PrintArea($args['id']);
        $args['path'] = $this->toolkit->getFilename("area_image", $args['instance']->get($args['property']), $args['id']);

        return $args;
    }

    function create($args)
    {
        return $this->toolkit->createImageConvertor($this->toolkit->getFilepath("area_image", $args['instance']->get($args['property']), $args['id']));
    }


    protected function createParts($args = array())
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

        /*if (empty($args['property']))
        {
           var_dump($args);
           exit;
        }
         *
         */
        
        $this->parts[] = $args['scope'];
        $this->parts[] = $args['property'];
        $this->parts[] = $level_1;
        $this->parts[] = $level_2;
        $info = pathinfo($args['path']);
        //$parts = explode("/", $args['path']);

        $file_parts = array();
        $file_parts[] = $args['id'];
        $file_parts[] = $args['w'] . "z" . $args['h'];
        if (!isset($args['clip']))
        {
           // _D(debug_backtrace(), true);
           // die();
        }
        $file_parts[] = $args['clip'] ? "1" : "0";
        $file_parts[] = md5($args['path']);

        $filename = implode("_", $file_parts);

        if (!empty($info['extension']))
        {
            $filename .= "." . $info['extension'];
        }
        $this->parts[] = $filename;

    }
    
}