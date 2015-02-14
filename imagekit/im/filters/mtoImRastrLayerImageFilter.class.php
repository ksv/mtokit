<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoImRastrLayerImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $width = $container->getWidth();
    //var_dump($width);
    
    $height = $container->getHeight();
    
    $file = $this->getParam('file');
    $img_cts = $this->getParam('image_cts');

    $wm_cont = new Imagick();
    if (!empty($file))
    {
        $wm_cont->readImage($file);
    } else if(!empty($img_cts))
    {
        $wm_cont->readImageBlob($img_cts);
    }
    //$wm_cont ->setImageMatte( true );
    $angle = (int)$this->getParam('rotate');
    $v_ref = (int)$this->getParam('v_reflect');
    $h_ref = (int)$this->getParam('h_reflect');

    if ($v_ref > 0)
    {
        $wm_cont->flipImage();
    }
    if ($h_ref > 0)
    {
        $wm_cont->flopImage();
    }
    if ($angle !== 0)
    {
        $wm_cont->rotateImage(new ImagickPixel('transparent'), $angle);
    }

  
    $wm_cont->thumbnailImage($this->getParam('width'),$this->getParam('height'),1);
    
    $container->getResource()->compositeImage($wm_cont, Imagick::COMPOSITE_OVER, $this->getParam('x'), $this->getParam('y'), Imagick::CHANNEL_ALL);
    //$container->getResource()->enhanceImage();
  }

  
}
