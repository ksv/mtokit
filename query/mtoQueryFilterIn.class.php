<?php
require_once("mtokit/query/mtoQueryFilterAbstract.class.php");
class mtoQueryFilterIn extends mtoQueryFilterAbstract
{


    function getSqlWhere()
    {
        if (!$this->use_sphinx)
        {
            return $this->args['field'] . " in (" . implode(",", $this->args['values']) . ")";
        }
    }


    function getSphinxArgs()
    {
        if ($this->use_sphinx)
        {
            return "filter=" . $this->args['field'] . "," . implode($this->args['values']);
        }
    }
}