<?php

abstract class mtoAbstractImageContainer
{

  protected $output_type = '';

  function setOutputType($type)
  {
    $this->output_type = $type;
  }

  function getOutputType()
  {
    return $this->output_type;
  }

  abstract function load($file_name, $type = '');

  abstract function save($file_name = null);

}
