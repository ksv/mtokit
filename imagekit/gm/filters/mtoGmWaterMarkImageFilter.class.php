<?php
mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");

class mtoGmWaterMarkImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $width = $container->getWidth();
    $height = $container->getHeight();
    $wm_cont = new Gmagick();
    $wm_cont->readImage($this->getWaterMark());
    list($x, $y) = $this->calcPosition($this->getX(), $this->getY(), $width, $height);
    $container->getResource()->compositeImage($wm_cont, Gmagick::COMPOSITE_OVER, $x, $y);
  }

  function calcPosition($x, $y, $width, $height)
  {
  	if($x >= 0 && $y >= 0)
      return array($x, $y);
    if($x < 0)
      $x += $width;
    if($y < 0)
      $y += $height;
    return array($x, $y);
  }

  function getWaterMark()
  {
  	return $this->getParam('water_mark');
  }

  function getX()
  {
    return $this->getParam('x', 0);
  }

  function getY()
  {
    return $this->getParam('y', 0);
  }

  function getOpacity()
  {
    return $this->getParam('opacity', 0);
  }
}
