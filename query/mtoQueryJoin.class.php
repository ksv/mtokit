<?php
class mtoQueryJoin
{
    private $type;
    private $left;
    private $right;
    private $tbl;


    function __construct($type, $tbl, $left, $right)
    {
        $this->type = $type;
        $this->left = $left;
        $this->right = $right;
        $this->tbl = $tbl;
    }

    function getJoin()
    {
        return $this->type . " join " . $this->tbl . " on tbl." . $this->left . " = " . $this->right;
    }
}