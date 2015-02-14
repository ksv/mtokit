<?php
require_once("mtokit/query/mtoQueryFilterAbstract.class.php");
class mtoQueryFilterNotField extends mtoQueryFilterAbstract
{


    function getSqlWhere()
    {
        if (!$this->use_sphinx)
        {
            return $this->args['field'] . " != '" . $this->args['value'] . "'";
        }
    }


    function getSphinxArgs()
    {
        if ($this->use_sphinx)
        {
            return "!filter=" . $this->args['field'] . "," . $this->args['value'];
        }
    }
}