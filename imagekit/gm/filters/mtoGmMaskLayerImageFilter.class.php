<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoGmMaskLayerImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $width = $container->getWidth();
    $height = $container->getHeight();

    $file = $this->getParam('file');
    

    $wm_cont = new Gmagick();
    $wm_cont->readImage($file);

    $container->getResource()->compositeImage($wm_cont, Gmagick::COMPOSITE_OVER, $this->getParam('x',0), $this->getParam('y',0));
    $container->getResource()->enhanceImage();
    
  }

  
}
