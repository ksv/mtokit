<?php
mtoClass :: import("mtokit/cache/generator/mtoCommonFilenameGenerator.class.php");

class mtoMediaFilenameGenerator extends mtoCommonFilenameGenerator
{
    function parse($args = array())
    {
        $params = array();
        $l1 = array_shift($args);
        $l2 = array_shift($args);
        $filename = array_shift($args);
        $parts = explode("_", $filename);
        $params['id'] = array_shift($parts);
        list($params['w'], $params['h']) = explode("z", array_shift($parts));
        $params['clip'] = array_shift($parts);
        $params['type'] = array_shift($parts);
        if (!isset($params['id']))
        {
            return $params;
        }
        $params['instance'] = new Media($params['id']);
        //$params['owner_id'] = $media->get("owner");
        $params['path'] = $this->toolkit->getFilename("media_image_common", $params['instance']->get("filename"), $params['id']);
        return $params;
    }


    function create($args)
    {           
        $fname = $this->toolkit->getFilepath("media_image_common", $args['instance']->get("filename"), $args['id']);
        if (file_exists($fname) && is_file($fname))
        {
            return $this->toolkit->createImageConvertor($fname);
        }
        else
        {
            throw new Exception("NOMEDIA");
        }
    }
    
    function delete($args)
    {
        $args['scope'] = $this->parent->getScope();
        $this->parts = array();
        $this->appendDir($args);
        $dir = mtoConf :: instance()->getFile("cache_args", "path");
        $dir .= "/" . implode("/", $this->parts);
        $list = mtoFs :: ls($dir);
        foreach ($list as $file)
        {
            if (preg_match("#^".$args['id']."_#", $file))
            {
                unlink($dir . "/" . $file);
            }
        }
        mtoProfiler :: instance()->logDebug("Cache removed for media id=" . $args['id'], "cdn_delete");
        return true;
    }


    protected function createParts($args = array())
    {
        $this->appendDir($args);
        $info = pathinfo($args['path']);
        //$parts = explode("/", $args['path']);

        $file_parts = array();
        $file_parts[] = $args['id'];
        $file_parts[] = $args['w'] . "z" . $args['h'];
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