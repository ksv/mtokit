<?php

mtoClass :: import("mtokit/cache/generator/mtoCommonFilenameGenerator.class.php");

class mtoBlogLinkFilenameGenerator extends mtoCommonFilenameGenerator
{

    function parse($args = array())
    {
        $params = array();
        $l1 = array_shift($args);
        $l2 = array_shift($args);
        $params['id'] = array_shift($args);
        $params['instance'] = new Product($params['id']);
        $filename = array_pop($args);
        $parts = explode("_", $filename);
        list($params['w'], $params['h']) = explode("z", array_shift($parts));
        
        $params['color'] = array_shift($parts);
        $params['bcolor'] = array_shift($parts);

        $params['path'] = array_pop($parts);
        return $params;



    }



    function create($args)
    {
        if (empty($args['bcolor']))
        {
            $args['bcolor'] = 'FFFFFF';
        }

        if (empty($args['color']))
        {
            $args['color'] = '000000';
        }


        $image = new Imagick();
        $image->newImage(240, 280, new ImagickPixel("white"));
        $image->setImageFormat("png");

        $img = $args['instance']->build_image(array('type' => "tov", 'side' => $args['instance']->productInfo['tov_show_side']));
        $img->cropThumbnailImage(240, 240);
        $img2 = new Imagick();
        $img2->newImage($img->getImageWidth(), $img->getImageHeight(), new ImagickPixel("white"));
        $img2->compositeImage($img, Imagick::COMPOSITE_OVER, 0, 0);

        $image->compositeImage($img2, Imagick::COMPOSITE_DARKEN, 0, 0, Imagick::CHANNEL_ALL);
        $line_draw = new ImagickDraw();
        $line_draw->setStrokeColor(new ImagickPixel("#000000"));
        $line_draw->line(0, 241, 240, 241);
        $ticket_color = new ImagickPixel("#".$args['bcolor']);
        //$ticket_color = new ImagickPixel("#000000");
        $text_color = new ImagickPixel("#".$args['color']);
        //$text_color = new ImagickPixel("#000000");

        $rect_draw = new ImagickDraw();
        $rect_draw->setStrokeColor($ticket_color);
        $rect_draw->setFillColor($ticket_color);
        $rect_draw->rectangle(0, 241, 240, 241);
        $text_draw = new ImagickDraw();
        $text_draw -> setFont("images/arial.ttf");
        $text_draw -> setFontSize(12);
        $text_draw -> setFontWeight(100);
        $text_draw -> setTextAlignment(Imagick::ALIGN_CENTER);
        $text_draw -> setTextAntialias(true);
        $text_draw -> setFillColor($text_color);
        $text_draw -> setStrokeWidth(1);

        $icon = new Imagick();
        $icon->readImage("images/icons/external_logo_icon.png");
        $image->drawImage($line_draw);
        $image->drawImage($rect_draw);
        $image->annotateImage($text_draw, 120, 266, 0, $args['instance']->get_name());
        //$image->annotateImage($text_draw, 120, 266, 0, "dwfwefwe");

        $icon->cropThumbnailImage(14, 14);
        $image->compositeImage($icon, Imagick::COMPOSITE_REPLACE, 0, 266, Imagick::CHANNEL_ALL);

        return $this->toolkit->createImageConvertor($image);
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
        return true;
    }

    protected function createParts($args = array())
    {
        $this->appendDir($args);
        $this->parts[] = $args['id'];
        $fileparts = array();
        $fileparts[] = $args['w'] . "z" . $args['h'];
        $fileparts[] = $args['color'];
        $fileparts[] = $args['bcolor'];
        $fileparts[] = md5($args['id'] . $args['color'] . $args['bcolor']) . ".png";
        $this->parts[] = implode("_", $fileparts);


    }




}