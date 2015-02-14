<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoImCropImageFilter extends mtoAbstractImageFilter
{
  function apply(mtoAbstractImageContainer $container)
  {
    list($x, $y, $width, $height) = $this->calculateCropArea($container->getWidth(), $container->getHeight());
    $container->getResource()->cropImage($width, $height, $x, $y);
  }

  function calculateCropArea($image_width, $image_height)
  {
    $width = $this->getWidth();
    $height = $this->getHeight();
    if($width === null)
      $width = $image_width;
    if($height === null)
      $height = $image_height;

    $x = $this->getX();
    $y = $this->getY();

    if($x + $width > $image_width)
      $width -= $x + $width - $image_width;
    if($y + $height > $image_height)
      $height -= $y + $height - $image_height;

    return array($x, $y, $width, $height);
  }

  function getWidth()
  {
  	return $this->getParam('width');
  }

  function getHeight()
  {
    return $this->getParam('height');
  }

  function getX()
  {
    return $this->getParam('x', 0);
  }

  function getY()
  {
    return $this->getParam('y', 0);
  }

}