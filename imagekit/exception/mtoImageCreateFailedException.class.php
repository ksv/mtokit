<?php

class mtoImageCreateFailedException extends mtoException
{

  function __construct($file_name)
  {
  	parent::__construct('Image create is failed', array('file' => $file_name));
  }

}
