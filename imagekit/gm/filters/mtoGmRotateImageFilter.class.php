<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");

class mtoGmRotateImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $angle = $this->getAngle();
    if(!$angle)
      return;
    $bgcolor = $this->getBgColor();
    $image = $container->getResource();
    $image->rotateImage(new GmagickPixel("#".$bgcolor), $angle);
    $container->replaceResource($image);
  }

  function getAngle()
  {
    return $this->getParam('angle', 0);
  }

  function getBgColor()
  {
    return $this->getParam('bgcolor', 'FFFFFF');
  }
}
