<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoGmShadowLayerImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $image)
  {
    $w = $image->getWidth();
    $h = $image->getHeight();
    $x =0;
    $y = 3;
    
    $cl = $image->getResource()->clone();
    
    $image->getResource()->resizeImage($w-10,$h-10,Gmagick::FILTER_LANCZOS,1);
    
    
    $black_draw = new GmagickDraw();
    $white_draw = new GmagickDraw();
    $shadow_draw = new GmagickDraw();

    $black_draw -> setStrokeColor(new GmagickPixel("#999999"));
    $black_draw -> setFillColor(new GmagickPixel("#999999"));
    $white_draw -> setStrokeColor(new GmagickPixel("#FFFFFF"));
    $white_draw -> setFillColor(new GmagickPixel("#FFFFFF"));
    $shadow_draw -> setStrokeColor(new GmagickPixel("#C3C3C3"));
    $shadow_draw -> setFillColor(new GmagickPixel("#C3C3C3"));
    
    $white_draw->rectangle(0, 0, $w-1, $h-1);
    $shadow_draw->rectangle($x+3, $y+3, $x+$w-6, $y+$h-6);
    $black_draw->rectangle($x, $y, $x+$w-9, $y+$h-9); 
    $cl->drawImage($white_draw);
    $cl->drawImage($shadow_draw);
    $cl->drawImage($black_draw);
    
    $cl->compositeImage($image->getResource(), Gmagick::COMPOSITE_REPLACE, $x, $y);
    
    $image->replaceResource($cl);
    
    
  }

  
}
