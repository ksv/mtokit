<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoImMaskLayerImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $width = $container->getWidth();
    $height = $container->getHeight();

    $file = $this->getParam('file');
    

    $wm_cont = new Imagick();
    $wm_cont->readImage($file);

    $container->getResource()->compositeImage($wm_cont, Imagick::COMPOSITE_OVER, $this->getParam('x',0), $this->getParam('y',0), Imagick::CHANNEL_ALL);
    $container->getResource()->enhanceImage();
    
  }

  
}
