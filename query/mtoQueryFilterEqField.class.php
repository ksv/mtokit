<?php
require_once("mtokit/query/mtoQueryFilterField.class.php");
class mtoQueryFilterEqField extends mtoQueryFilterField
{
    function __construct($use_sphinx, $args)
    {
        parent :: __construct($use_sphinx, array(
            'field' => $args[0],
            'sign' => 'eq',
            'value' => $args[1]
        ));
    }

    function getSphinxArgs()
    {
        if ($this->use_sphinx)
        {
            return "filter=" . $this->args['field'] . "," . $this->args['value'];
        }
    }

}