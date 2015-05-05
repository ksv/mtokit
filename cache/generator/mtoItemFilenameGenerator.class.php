<?php

mtoClass :: import("mtokit/cache/generator/mtoCommonFilenameGenerator.class.php");

class mtoItemFilenameGenerator extends mtoCommonFilenameGenerator
{

    function parse($args = array())
    {
        $params = array();
        $l1 = array_shift($args);
        $l2 = array_shift($args);
        $filename = array_pop($args);
        $parts = explode("_", $filename);
        $params['id'] = array_shift($parts);
        list($params['w'], $params['h']) = explode("z", array_shift($parts));
        $params['side'] = array_shift($parts);
        $params['effect'] = array_shift($parts);
        $params['option_id'] = array_shift($parts);
        $params['path'] = $filename;
        $params['instance'] = new Item($params['id']);
        $option = $params['instance']->getOptionById($params['option_id']);
        if ($option)
        {
            $side = strtolower($option['opt_side']);
        }
        elseif (in_array($params['side'], array("front", "back")))
        {
            $side = $params['side'];
        }
        else
        {
            $side = "front";
        }
        $params['path'] = $this->toolkit->getFilepath("item", $params['instance']->get($side."_img"), $params['id']);
        return $params;
    }


    function create($args)
    {
        $size = getimagesize($args['path']);
        $img = new Imagick();
        $img->newImage($size[0], $size[1], new ImagickPixel("#FFFFFF"));
        $img->compositeImage(new Imagick($args['path']), Imagick :: COMPOSITE_OVER, 0, 0);
        //$conv = $this->toolkit->createImageConvertor($args['path']);
        $conv = $this->toolkit->createImageConvertor($img);
        $option = $args['instance']->getOptionById($args['option_id']);
        if (isset($args['effect']) && $option)
        {
            switch ($args['effect'])
            {
                case 1:
                    $conv->frame(
                            array(
                                'x' => $option['opt_x'],
                                'y' => $option['opt_y'],
                                'width' => $option['opt_w'],
                                'height' => $option['opt_h'],
                                'filled' => 1,
                                'dashed' => 0,
                                'color' => "FF:00:00")
                    );
                break;
            }
        }
        return $conv;
    }

    protected function createParts($args = array())
    {
        $info = pathinfo($args['path']);
        
        if (empty($args['option_id']))
        {
            $args['option_id'] = 0;
            if (isset($args['side']))
            {
                $side = $args['side'];
            }
            else
            {
                $side = "";
            }
        }
        else
        {
            $item = new Item($args['id']);
            $option = $item->getOptionById($args['option_id']);
            if ($option)
            {
                $side = strtolower($option['opt_side']);
            }
            else
            {
                $side = "";
            }
        }
        if (!isset($args['effect']))
        {
            $args['effect'] = 0;
        }
        else
        {
            if (!is_numeric($args['effect']))
            {
                $args['effect'] = constant($args['effect']);
            }
        }
        $this->appendDir($args);
        
        $fileparts = array();
        $fileparts[] = $args['id'];
        $fileparts[] = $args['w'] . "z" . $args['h'];
        $fileparts[] = $side;
        $fileparts[] = $args['effect'];
        $fileparts[] = $args['option_id'];
        $fileparts[] = md5($args['path']) . (!empty($info['extension']) ? "." . $info['extension'] : "");

        $this->parts[] = implode("_", $fileparts);
    }

    function delete($args = array())
    {
        //$args = $this->extract_args($args);
        $args['id'] = $args['path'];
        $dir = mtoConf :: instance()->getFile("cache_args", "path");
        $args['scope'] = $this->parent->getScope();
        $this->parts = array();
        $this->appendDir($args);
        $dir .= "/" . implode("/", $this->parts);
        mtoFs :: rm($dir);
        return true;
    }




}