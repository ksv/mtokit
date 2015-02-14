<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageContainer.class.php");
mtoClass :: import("mtokit/imagekit/exception/mtoImageTypeNotSupportedException.class.php");
mtoClass :: import("mtokit/imagekit/exception/mtoImageCreateFailedException.class.php");
mtoClass :: import("mtokit/imagekit/exception/mtoImageSaveFailedException.class.php");
mtoClass :: import("mtokit/imagekit/exception/mtoImageSaveFailedException.class.php");



class mtoImImageContainer extends mtoAbstractImageContainer
{

  protected static $supported_types = array('GIF', 'JPEG', 'PNG', 'WBMP', 'gif', 'jpeg', 'png', 'wbmp');

  protected $img;
  protected $img_type;
  protected $pallete;
  protected $out_type;

  function setOutputType($type)
  {
    if($type)
    {
      if(!self::supportSaveType($type))
      {
        throw new mtoImageTypeNotSupportedException($type);
      }
      $this->out_type = $type;
    }

    parent::setOutputType($type);
  }

  function load($file_name, $type = '')
  {

    $is_bmp = false;
    $this->destroyImage();

    $imginfo = @getimagesize($file_name);
    if (preg_match("#\.bmp$#i", $file_name))
    {
        $is_bmp = true;
    }
      
    if(!$imginfo && !$is_bmp)
    {
        //var_dump($imginfo);
      //die($file_name.' NOT FOUND');
      throw new mtoFileNotFoundException($file_name);
    }  


    $this->img = new Imagick();    
    
    $this->img->readImage($file_name);
    
    if (!($this->img instanceof Imagick))
      throw new mtoImageCreateFailedException($file_name);

    $this->img_type = $this->img->getImageFormat();
  }

  function save($file_name = null, $quality = null)
  {
    $type = $this->out_type;
    if(!$type)
      $type = $this->img_type;

//    if(!self::supportSaveType($type))
//      throw new mtoImageTypeNotSupportedException($type);

    if (!($this->img instanceof Imagick))
    {
        $msg = array();
        $msg[] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
        $msg[] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
        $msg[] = $this->img;
        $msg[] = _D(debug_backtrace(), true, true, true);
        $msg[] = "\n\n";
        mtoProfiler :: instance()->logDebug(implode("\t", $msg), "debug/save_no_instance");
    }
    $this->img->setImageFormat($type);
    $this->img->setImageFilename($file_name);
    
    if(!is_null($quality) && strtolower($type) == 'jpeg')
    {
      $this->img->setCompression(imagick::COMPRESSION_JPEG);
      $this->img->setCompressionQuality($quality);
    }
    
    if (!$this->img->writeImage($file_name))
      throw new mtoImageSaveFailedException($file_name);

    $this->destroyImage();
  }

  function getResource()
  {
    return $this->img;
  }

  function replaceResource($img)
  {
    $this->destroyImage();
    $this->img = $img;
  }
  
  function cloneResource()
  {
      $res = $this->img;
      $new_img = clone $res;
      $this->img = $new_img;
  }

  function isPallete()
  {
    return ($this->img->getImageColors() < 256);
  }

  function getWidth()
  {
    return $this->img->getImageWidth();
  }

  function getHeight()
  {
    return $this->img->getImageHeight();
  }

  function destroyImage()
  {
    if(!$this->img)
      return;
    $this->img = null;
  }



  static function supportLoadType($type)
  {
    return self::supportType($type);
  }

  static function supportSaveType($type)
  {
    return self::supportType($type);
  }

  static function supportType($type)
  {
    if(!class_exists('Imagick'))
      return false;
    return (boolean)(in_array($type, self::$supported_types));
  }

  static function convertImageType($type)
  {
    switch ($type)
    {
      case 2:
        return "JPEG";
      break;
      case 3:
        return "PNG";
      break;
      case 1:
        return "GIF";
      break;
      case 15:
        return "WBMP";
      break;
    }
  }

  function __destruct()
  {
    $this->destroyImage();
  }
}

