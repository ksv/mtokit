<?php
require_once("mtokit/query/mtoQueryFilterAbstract.class.php");
class mtoQueryFilterNotIn extends mtoQueryFilterAbstract
{


    function getSqlWhere()
    {
        if (!$this->use_sphinx)
        {
            return $this->args['field'] . " not in (" . implode(",", $this->args['values']) . ")";
        }
    }


    function getSphinxArgs()
    {
        if ($this->use_sphinx)
        {
            return "!filter=" . $this->args['field'] . "," . implode($this->args['values']);
        }
    }
}