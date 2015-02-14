<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoGmOutputImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $container->setOutputType($this->getType());
  }

  function getType()
  {
    return $this->getParam('type', '');
  }

}
