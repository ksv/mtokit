<?php
require_once("mtokit/query/mtoQueryFilterAbstract.class.php");
class mtoQueryFilterField extends mtoQueryFilterAbstract
{


    function getSqlWhere()
    {
        if (!$this->use_sphinx)
        {
            return $this->args['field'] . $this->convertSign($this->args['sign']) . "'" . $this->args['value'] . "'";
        }
    }


    function getSphinxArgs()
    {
        if ($this->use_sphinx)
        {
        }
    }
}