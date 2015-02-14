<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageContainer.class.php");
mtoClass :: import("mtokit/imagekit/gm/mtoGmImageContainer.class.php");
mtoClass :: import("mtokit/imagekit/exception/mtoImageLibraryNotInstalledException.class.php");


class mtoGmImageConvertor extends mtoAbstractImageConvertor
{

  function __construct($params = array())
  {
    if (!class_exists('Gmagick'))
      throw new mtoImageLibraryNotInstalledException('GMagick');

    if(!isset($params['filters_scan_dirs']))
      $params['filters_scan_dirs'] = dirname(__FILE__) . '/filters';
    parent::__construct($params);
  }

  protected function createFilter($name, $params)
  {
    $class = $this->loadFilter($name, 'Gm');
    return new $class($params);
  }

  protected function createImageContainer($file_name, $type = '')
  {
    $container = new mtoGmImageContainer();
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
      $src_type = mtoGmImageContainer::convertImageType($imginfo[2]);
    }
    if(!$dest_type)
      $dest_type = $src_type;
    return mtoGmImageContainer::supportLoadType($src_type) &&
           mtoGmImageContainer::supportSaveType($dest_type);
  }
}
