<?php
mtoClass :: import("mtokit/cache/generator/mtoCommonFilenameGenerator.class.php");
mtoClass :: import("classes/model/TovarStructure.class.php");

class mtoTovarStructureFilenameGenerator extends mtoCommonFilenameGenerator
{
    function parse($args = array())
    {
        $params = array();
        $l1 = array_shift($args);
        $l2 = array_shift($args);
        $filename = array_shift($args);
        $parts = explode("_", $filename);
        $params['id'] = array_shift($parts);
        $params['structure_id'] = array_shift($parts);

        list($params['w'], $params['h']) = explode("z", array_shift($parts));
        $params['clip'] = array_shift($parts);
        if (!isset($params['id']))
        {
            return $params;
        }
        //$params['instance'] = new Tovar($params['id']);
        $params['instance'] = new TovarStructure($params['structure_id']);
        $params['path'] = $params['instance']->getBasicPath();
        return $params;
    }


    function create($args)
    {        
        return $this->toolkit->createImageConvertor($args['instance']->getBasicPath());
    }


    protected function createParts($args = array())
    {        
        $this->appendDir($args);
        $info = pathinfo($args['path']);
        //$parts = explode("/", $args['path']);

        $file_parts = array();
        $file_parts[] = $args['id'];
        $file_parts[] = $args['structure_id'];
        $file_parts[] = $args['w'] . "z" . $args['h'];
        $file_parts[] = $args['clip'] ? "1" : "0";        
        $file_parts[] = md5($args['path']);

        $filename = implode("_", $file_parts);
        $filename .= ".jpg";

        $this->parts[] = $filename;

    }
    
}