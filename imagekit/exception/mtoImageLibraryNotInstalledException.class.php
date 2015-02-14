<?php

class mtoImageLibraryNotInstalledException extends mtoException
{

  function __construct($lib_name)
  {
  	parent::__construct('Library not installed', array('file' => $lib_name));
  }

}