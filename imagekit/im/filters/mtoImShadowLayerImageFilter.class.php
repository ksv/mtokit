<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoImShadowLayerImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $image)
  {
    $w = $image->getWidth();
    $h = $image->getHeight();
    $x =0;
    $y = 3;
    
    $cl = $image->getResource()->clone();
    
    $image->getResource()->resizeImage($w-10,$h-10,Imagick::FILTER_LANCZOS,1);
    
    
    $black_draw = new ImagickDraw();
    $white_draw = new ImagickDraw();
    $shadow_draw = new ImagickDraw();

    $black_draw -> setStrokeColor(new ImagickPixel("#999999"));
    $black_draw -> setFillColor(new ImagickPixel("#999999"));
    $white_draw -> setStrokeColor(new ImagickPixel("#FFFFFF"));
    $white_draw -> setFillColor(new ImagickPixel("#FFFFFF"));
    $shadow_draw -> setStrokeColor(new ImagickPixel("#C3C3C3"));
    $shadow_draw -> setFillColor(new ImagickPixel("#C3C3C3"));
    
    $white_draw->rectangle(0, 0, $w-1, $h-1);
    $shadow_draw->rectangle($x+3, $y+3, $x+$w-6, $y+$h-6);
    $black_draw->rectangle($x, $y, $x+$w-9, $y+$h-9); 
    $cl->drawImage($white_draw);
    $cl->drawImage($shadow_draw);
    $cl->drawImage($black_draw);
    
    $cl->compositeImage($image->getResource(), Imagick::COMPOSITE_REPLACE, $x, $y, Imagick::CHANNEL_ALL);
    
    $image->replaceResource($cl);
    
    
  }

  
}
