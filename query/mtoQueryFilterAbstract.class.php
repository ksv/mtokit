<?php
abstract class mtoQueryFilterAbstract
{
    protected $use_sphinx;
    protected $args;


    function __construct($use_sphinx, $args)
    {
        $this->use_sphinx = $use_sphinx;
        $this->args = $args;
    }

    //FIXME
    static function createFilter($type, $use_sphinx, $args)
    {
        $classname = "mtoQueryFilter" . mto_camel_case($type);
        require_once "mtokit/query/".$classname.".class.php";
        return new $classname($use_sphinx, $args);
    }

    abstract function getSqlWhere();
    abstract function getSphinxArgs();



    protected function convertSign($sign)
    {
        switch($sign)
        {
            case 'leq':
                return '<=';
            break;

            case 'meq':
                return '>=';
            break;

            case 'eq':
                return '=';
            break;

            default:
                throw new mtoQueryException('Unsuported filter sign type');
            break;

        }
    }

}