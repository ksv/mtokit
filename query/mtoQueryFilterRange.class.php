<?php
require_once("mtokit/query/mtoQueryFilterAbstract.class.php");
class mtoQueryFilterRange extends mtoQueryFilterAbstract
{


    function getSqlWhere()
    {
        if (!$this->use_sphinx)
        {

            return $this->args['field'] . " between " . $this->args['min'] . " and " . $this->args['max'];
        }
    }


    function getSphinxArgs()
    {
        if ($this->use_sphinx)
        {
            return "range=" . $this->args['field'] . "," . $this->args['min'] . "," . $this->args['max'];
        }
    }
}