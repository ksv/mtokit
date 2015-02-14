<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageContainer.class.php");
mtoClass :: import("mtokit/imagekit/im/mtoImImageContainer.class.php");
mtoClass :: import("mtokit/imagekit/exception/mtoImageLibraryNotInstalledException.class.php");


class mtoImImageConvertor extends mtoAbstractImageConvertor
{

  function __construct($params = array())
  {
    if (!class_exists('Imagick'))
      throw new mtoImageLibraryNotInstalledException('ImageMagick');

    if(!isset($params['filters_scan_dirs']))
      $params['filters_scan_dirs'] = dirname(__FILE__) . '/filters';
    parent::__construct($params);
  }

  protected function createFilter($name, $params)
  {
    $class = $this->loadFilter($name, 'Im');
    return new $class($params);
  }

  protected function createImageContainer($file_name, $type = '')
  {
    $container = new mtoImImageContainer();
    if (!empty($file_name))
    {
        $container->load($file_name, $type);
    }
    return $container;
  }

  function isSupportConversion($file, $src_type = '', $dest_type = '')
  {
    if(!$src_type)
    {
      $imginfo = getimagesize($file);
      if(!$imginfo)
        throw new mtoFileNotFoundException($file);
      $src_type = mtoImImageContainer::convertImageType($imginfo[2]);
    }
    if(!$dest_type)
      $dest_type = $src_type;
    return mtoImImageContainer::supportLoadType($src_type) &&
           mtoImImageContainer::supportSaveType($dest_type);
  }
}
