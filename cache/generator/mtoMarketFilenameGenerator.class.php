<?php

mtoClass :: import("mtokit/cache/generator/mtoCommonFilenameGenerator.class.php");

class mtoMarketFilenameGenerator extends mtoCommonFilenameGenerator
{

    function parse($args = array())
    {
        $params = array();
        $l1 = array_shift($args);
        $l2 = array_shift($args);
        $params['id'] = array_shift($args);
        $params['instance'] = new Product($params['id']);
        $params['generate_type'] = array_shift($args);
        if ($params['generate_type'] == "model")
        {
            $params['model_side'] = array_shift($args);
        }
        else
        {
            $x = array_shift($args);
            if ($x == "3d")
            {
                $params['use_3dmask'] = 1;
            }
        }
        $filename = array_pop($args);
        $parts = explode("_", $filename);
        list($params['w'], $params['h']) = explode("z", array_shift($parts));
        if (empty($params['w']) || empty($params['h']))
        {
            mtoProfiler :: instance()->logDebug($_SERVER['REQUEST_URI'] . "\t[".(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "")."]", "debug/cache_no_size");
        }
        $params['side'] = array_shift($parts);
        $params['option_id'] = array_shift($parts);
        $item = array_shift($parts);
        if (intval($item) > 0)
        {
            $params['instance']->overrideItem(intval($item));
        }
        if ($params['option_id'])
        {
            $params['instance']->overrideOption(intval($params['option_id']));
        }
        $params['reflect'] = array_shift($parts);
        if (intval(array_shift($parts)) > 0)
        {
            $params['instance']->useBlankItem();
        }
        $params['path'] = $params['instance'];
        return $params;
        
//        $filename = array_shift($args);
//        $parts = explode("_", $filename);
//        $params['id'] = array_shift($parts);
//        list($params['w'], $params['h']) = explode("z", array_shift($parts));
//        $params['clip'] = array_shift($parts);
//        return $params;


//    $rstring  = preg_replace("#^(/+.*/)#U","",$_SERVER['REQUEST_URI']);
//
//    $partsArr = explode('/',$rstring);
//
//    if ($partsArr[0] == 'product')
//    {
//        //process new url
//        $product_id = (int)$partsArr[3];
//        $productObject = new Product($product_id);
//
//        $type = $partsArr[4];
//
//        if ($type == 'tov')
//        {
//            //process tovar
//
//
//
//            $fname = $partsArr[6];
//            $fnameSplit = explode('_',$fname);
//            $sizeStr = $fnameSplit[0];
//            $sizeArr = explode('z',$sizeStr);
//            $side = $fnameSplit[1];
//            $option = $fnameSplit[2];
//            $override_item = $fnameSplit[3];
//            $reflect = $fnameSplit[4];
//            $use_blank = $fnameSplit[5];
//
//            $thumbnailObj = new CacheThumbnailer(array(
//                            'type' => 'product',
//                            'tovar_id' => $product_id,
//                            'user_id' => $productObject->productInfo['tov_owner'],
//                            'object_info' => $side,
//                            'option_id' => $option,
//                            "side" => $side,
//                            'product_object' => $productObject,
//                            //'img_size' => $this->productInfo['default_option']['tm_scale'],
//                            'w' => $sizeArr[0],
//                            'h' => $sizeArr[1],
//                            'override_item' => $override_item,
//                            'use_blank_item' => $use_blank
//                        ));
//
//
//            $url = $thumbnailObj->getUrl(false);
//
//
//        } else
//        {
//            //process model
//            $side = $partsArr[5];
//            $fname = $partsArr[6];
//            $fnameSplit = explode('_',$fname);
//            $sizeStr = $fnameSplit[0];
//            $sizeArr = explode('z',$sizeStr);
//            $override_item = $fnameSplit[3];
//            $reflect = $fnameSplit[4];
//            $use_blank = $fnameSplit[5];
//
//            if ($use_blank>0)
//            {
//                $productObject->useBlankItem();
//            }
//
//            if ($override_item>0)
//            {
//                $productObject->overrideItem($override_item);
//            }
//            $url = $productObject->get_model_image_url($side, $sizeArr[0],$sizeArr[1],0);
//
//        }


    }

    function extract_args($args)
    {
//        $args['id'] = $args['instance']->productInfo['tov_id'];
//        $args['skey'] = $args['instance']->productInfo['tov_owner'];
//        if ($args['instance']->productInfo['item_is_canvas'])
//        {
//            $args['use_3dmask'] = 1;
//        }
//        if (empty($args['changed']))
//        {
//            $args['changed'] = $args['instance']->productInfo['tov_last_changed'];
//        }
        return $args;
    }


    function create($args)
    {
        if (!isset($args['generate_type']))
        {
            mtoProfiler :: instance()->logCatchError("generate type not defined");
        }
        if ($args['instance']->productInfo['tov_deleted'] == 1 && empty($args['for_order']))
        {
            //mtoProfiler :: instance()->logDebug($args['instance']->productInfo['tov_id'], "debug/gen_deleted");
            throw new Exception("DELETED");
            exit;
        }
        if ($args['generate_type'] == "model")
        {
            mtoProfiler :: instance()->timerStartPinba("img_inner1", array('scope' => "img", 'operation' => "gen", 'suffix' => mtoConf :: instance()->get("core", "suffix"), 'fullop' => "img::gen::model"));
            //$image = $args['instance']->generate_model_image($args['side'],$args['w'],$args['h']);
            $image = $args['instance']->build_image(array('type' => 'model', 'side' => $args['side']));
            mtoProfiler :: instance()->timerStopPinba("img_inner1");
        }
        else
        {
            if ($args['instance']->isPrint() || $args['instance']->isSticker())
            {
                mtoProfiler :: instance()->timerStartPinba("img_inner2", array('scope' => "img", 'operation' => "gen", 'suffix' => mtoConf :: instance()->get("core", "suffix"), 'fullop' => "img::gen::print"));
                //$image = $args['instance']->generate_print_image($args['side']);
                $image = $args['instance']->build_image(array('type' => "tov"));
                mtoProfiler :: instance()->timerStopPinba("img_inner2");
            }
            else
            {
                if (isset($args['generate_type']) && $args['generate_type'] == "crop")
                {
                    $params = array("crop_design" => 1);
                }
                else
                {
                    $params = array();
                }
                mtoProfiler :: instance()->timerStartPinba("img_inner3", array('scope' => "img", 'operation' => "gen", 'suffix' => mtoConf :: instance()->get("core", "suffix"), 'fullop' => "img::gen::simple"));
                //$image = $args['instance']->generate_image($args['side'], false, 0, 0, $params);
                $image = $args['instance']->build_image(array(
                    'type' => "tov", 
                    'side' => $args['side'], 
                    'crop_design' => (isset($args['generate_type']) && $args['generate_type'] == "crop"),
                    'use_3dmask' => !empty($args['use_3dmask'])
                ));
                mtoProfiler :: instance()->timerStopPinba("img_inner3");
            }
        }
        if ($image)
        {
            return $this->toolkit->createImageConvertor($image);
        }
        else
        {
            mtoProfiler :: instance()->logDebug("NOIMAGE", "debug/rest");
        }
    }

    function delete($args)
    {
        $args = $this->extract_args($args);
        $dir = mtoConf :: instance()->getFile("cache_args", "path");
        $args['scope'] = $this->parent->getScope();
        $this->parts = array();
        $this->appendDir($args);
        $this->parts[] = $args['id'];
        $dir .= "/" . implode("/", $this->parts);
        mtoFs :: rm($dir);
        mtoProfiler :: instance()->logDebug("Cache removed for id=" . $args['id'], "cdn_delete");
        return true;
    }

    protected function createParts($args = array())
    {
        //var_dump($args['instance']->productInfo['default_option']['tm_opt']);
        //$args['option_id'] = isset($args['instance']->productInfo['default_option']['tm_opt']) ? $args['instance']->productInfo['default_option']['tm_opt'] : '0';
        //$o = $args['instance']->option_by_side(empty($args['side']) ? "front" : $args['side']);
        $args['option_id'] = '0';
        
        $args['override_item'] = 0;
        $args['use_blank_item'] = 0;
        $this->appendDir($args);
        $this->parts[] = $args['id'];
        
        if(isset($args['generate_type']) && $args['generate_type'] == "crop")
        {
            $this->parts[] = "crop";
            if (!empty($args['use_3dmask']))
            {
                $this->parts[] = "3d";
            }
            else
            {
                $this->parts[] = "all";
            }
            $ext = 'jpg';
        }
        else
        {
            $this->parts[] = "tov";
            if (!empty($args['use_3dmask']))
            {
                $this->parts[] = "3d";
            }
            else
            {
                $this->parts[] = "all";
            }
            $ext = 'jpg';
        }
        $fileparts = array();
        $fileparts[] = $args['w'] . "z" . $args['h'];
        $fileparts[] = empty($args['side']) ? "front" : $args['side'];
        $fileparts[] = empty($args['option_id']) ? '0' : $args['option_id'];
        $fileparts[] = empty($args['override_item']) ? '0' : $args['override_item'];
        $fileparts[] = empty($args['reflect']) ? '0' : $args['reflect'];
        $fileparts[] = empty($args['use_blank_item']) ? '0' : $args['use_blank_item'];
        $fileparts[] = md5($args['id'] . "x" . (empty($args['side']) ? "front" : $args['side']) . (empty($args['option_id']) ? "0" : $args['option_id'])) . "." . $ext;
        //var_dump("AA:" . md5($args['id'] . "x" . (empty($args['side']) ? "front" : $args['side']) . (empty($args['option_id']) ? "0" : $args['option_id'])));

        $this->parts[] = implode("_", $fileparts);
    }

}