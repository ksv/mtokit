<?php

class mtoImageTypeNotSupportedException extends mtoException
{

  function __construct($type = '')
  {
  	parent::__construct('Image type is not supported', $type ? array('type' => $type) : array());
  }

}
