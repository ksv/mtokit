<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoImFontLayerImageFilter extends mtoAbstractImageFilter
{

  function apply(lmbAbstractImageContainer $container)
  {
    $width = $container->getWidth();
    $height = $container->getHeight();
    $db = mtoToolkit :: instance()->getDbConnection();
    $toolkit = mtoToolkit::instance();

    $layerObj = $this->getParam('object');
    $lines = $layerObj->getLines()->sort('priority');

    //var_dump($this->getParam('all_width'));
    
    $offset_all_top = $toolkit->convertMm2Px($layerObj->getContOffsetY(),$this->getParam('all_height'),$height);
    $offset_all_left = $toolkit->convertMm2Px($layerObj->getContOffsetX(),$this->getParam('all_width'),$width);

    $canvas_all = new Imagick();
    $canvas_all_width = $toolkit->convertMm2Px($layerObj->getWidth()*$layerObj->getScale(),$this->getParam('all_width'),$width);
    $canvas_all_height =  $toolkit->convertMm2Px($layerObj->getHeight()*$layerObj->getScale(),$this->getParam('all_height'),$height);

    $canvas_all->newImage($canvas_all_width,$canvas_all_height,  new ImagickPixel( "transparent" ),'png');

    $offset_top = 5*$layerObj->getScale();
    $offset_left = 0;

    foreach($lines as $ind=> $line)
    {

        
        $fontObj =  new Font((int)$line->getFontId());

        $draw = new ImagickDraw();
        $canvas = new Imagick();
        
        $box_width = $toolkit->convertMm2Px($line->getWidth()*$layerObj->getScale(),$this->getParam('all_width'),$width);
        $box_height = $toolkit->convertMm2Px($line->getHeight()*$layerObj->getScale(),$this->getParam('all_height'),$height);

        // set font
        $draw->setFont($fontObj->getTtfPath($line->getFontItalic(),$line->getFontBold()));
        $draw->setFontSize($box_height);

        $color_arr = $db->sql_getone('select color_hex from print_method_color where color_print_method_id=? and color_base_color_id=?',array($layerObj->getPrintMethodId(),$line->get('font_color')));
        $color_hex = $color_arr['color_hex'];
        //var_dump($color_hex);
        $draw->setFillColor($color_hex);

        $metrics = $canvas->queryFontMetrics($draw,$line->getFontText());
        //var_dump($metrics);
        $canvas->newImage($metrics['textWidth']+5,$metrics['textHeight'],  new ImagickPixel( "transparent" ),'png');
        $canvas->annotateImage($draw, 0, $metrics['textHeight']+$metrics['descender'], 0, $line->getFontText());

        $canvas->resizeImage($box_width+3,$box_height,Imagick::FILTER_CATROM,1);

        switch($line->getFontAlign())
        {
            case 'right':

                $offset_l = $canvas_all_width - $box_width+3 - 4*$layerObj->getScale();

            break;

            case 'center':                
                $offset_l = floor(($canvas_all_width/2) - (($box_width+3)/2));                
            break;

            default:
                $offset_l = $offset_left+4*$layerObj->getScale();
            break;


        }



        //$container->getResource()->annotateImage($draw, $offset_left, $offset_top, $layerObj->getRotation(), $line->getFontText());
        $offset_top += 3*$layerObj->getScale();

        $canvas_all->compositeImage($canvas, Imagick::COMPOSITE_OVER, $offset_l,$offset_top, Imagick::CHANNEL_ALL);

        $offset_top += $box_height;
        

    }


    $angle = (double)$layerObj->getRotation();
    if ($angle !== 0)
    {
        $canvas_all->rotateImage(new ImagickPixel('transparent'), $angle);
    }

    

    
    $container->getResource()->compositeImage($canvas_all, Imagick::COMPOSITE_OVER, $offset_all_left,$offset_all_top, Imagick::CHANNEL_ALL);

//    $output = $container->getResource()->getimageblob();
//    $outputtype = $container->getResource()->getFormat();
//    header("Content-type: $outputtype");
//    echo $output;
    
    
  }

  
}
