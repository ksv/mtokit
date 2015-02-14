<?php
require_once("mtokit/query/mtoQueryException.class.php");
require_once("mtokit/query/mtoQueryFilterAbstract.class.php");
require_once("mtokit/query/mtoQueryJoin.class.php");
class mtoQueryBuilder
{
    private $filters;
    protected $sql_table;
    protected $sql_pk;
    protected $sphinx_table;
    protected $sql_where = array();
    protected $sql_order = array();
    protected $sphinx_args = array();
    protected $sphinx_query = "";
    protected $sphinx_order = array();
    protected $has_sphinx = false;
    protected $joins = array();
    protected $group_by = array();


    function  __construct($sql_table, $sql_pk=null, $sphinx_table = null)
    {
        $this->sql_table = $sql_table;
        $this->sql_pk = $sql_pk;
        $this->sphinx_table = $sphinx_table;
        $this->has_sphinx = !empty($this->sphinx_table);
    }

    function addFilter($type, $args, $has_sphinx = null)
    {
        $sph = $has_sphinx === false ? false : $this->has_sphinx;
        $this->filters[] = mtoQueryFilterAbstract :: createFilter($type, $sph, $args);
        return $this;
    }

    function leftJoin($table, $field1, $field2)
    {
        $this->joins[] = new mtoQueryJoin("left", $table, $field1, $field2);
        return $this;
    }

    function rightJoin($table, $field1, $field2)
    {
        $this->joins[] = new mtoQueryJoin("right", $table, $field1, $field2);
        return $this;
    }

    function innerJoin($table, $field1, $field2)
    {
        $this->joins[] = new mtoQueryJoin("inner", $table, $field1, $field2);
        return $this;
    }

    function groupBy($field)
    {
        $this->group_by[] = $field;
        return $this;
    }

    function orderBy($field, $direction)
    {
        $this->sql_order[$field] = $direction;
        return $this;
    }

    function setFulltextKeyword($keyword)
    {
        $this->sphinx_query = $keyword;
    }

    function getQuery()
    {
        $this->collect();
        $sql_parts = array();
        if ($this->has_sphinx)
        {
            $sql_parts[] = "select sql_no_cache idx.id as sdoc_id, tbl.* from {$this->sphinx_table} as idx inner join {$this->sql_table} as tbl on idx.id=tbl.{$this->sql_pk}";
        }
        else
        {
            $sql_parts[] = "select tbl.* from {$this->sql_table}";
        }
        foreach ($this->joins as $join)
        {
            $sql_parts[] = $join->getJoin();
        }
        $where = array();
        if ($this->has_sphinx)
        {
            $where[] = "idx.query='" . $this->buildSphinxQuery() . "'";
        }
        foreach ($this->sql_where as $cond)
        {
            $where[] = $c;
        }
        if (!empty($where))
        {
            $sql_parts[] = " where " . implode(" and \n", $where);
        }
        if (!empty($this->group_by))
        {
            $sql_parts[] = " group by " . implode(",", $this->group_by);
        }
        $order = array();
        foreach ($this->sql_order as $field => $direction)
        {
            $order[] = $field . " " . $direction;
        }
        if (!empty($order))
        {
            $sql_parts[] = " order by " . implode($order);
        }

        return implode(" \n", $sql_parts);
    }

    function buildSphinxQuery()
    {
        $args = $this->sphinx_args;
        array_unshift($args, $this->sphinx_query);
        if (!empty($this->sphinx_order))
        {
            $args[] = "sort=extended:" . implode(",", $this->sphinx_order);
        }
        return implode(";", $args);
//        if ($limit)
//        {
//            $sphinx_args = $this->sphinx_args;
//            $limit_found = false;
//            $offset_found = false;
//            foreach ($sphinx_args as $k => $v)
//            {
//                if (strpos($v, "limit=") === 0)
//                {
//                    $sphinx_args[$k] = "limit=" . $limit;
//                    $limit_found = true;
//                }
//                if (strpos($v, "offset=") === 0)
//                {
//                    $sphinx_args[$k] = "offset=" . intval($this->offset);
//                    $offset_found = true;
//                }
//            }
//            if (!$limit_found)
//            {
//                $sphinx_args[] = "limit=" . $limit;
//            }
//            if (!$offset_found)
//            {
//                $sphinx_args[] = "offset=" . intval($this->offset);
//            }
//        }
//        else
//        {
//            $sphinx_args = $this->sphinx_args;
//        }



    }

    function collect()
    {
        $this->sql_where = array();
        $this->sphinx_args = array();
        //$this->sql_order = array();
        $this->sphinx_order = array();
        foreach ($this->filters as $filter)
        {
            if ($where = $filter->getSqlWhere())
            {
                $this->sql_where[] = $where;
            }
//            if ($order = $filter->getSqlOrder())
//            {
//                $this->sql_order[] = $order;
//            }
            if ($sph = $filter->getSphinxArgs())
            {
                $this->sphinx_args[] = $sph;
            }
//            if ($order = $filter->getSphinxOrder())
//            {
//                $this->sphinx_order[] = $order;
//            }
        }
    }


    function decorate($args)
    {
        foreach ($args as $k => $v)
        {
            if (strpos($k, "order_") === 0)
            {
                $this->sql_order[] = str_replace("order_", "", $k) . " " . $v;
            }
            elseif (strpos($k, "where_") === 0)
            {
                $this->sql_where[] = str_replace("where_", "", $k) . "='" . $this->toolkit->sqlEscape($v) . "'";
            }
            elseif ($k == "sphinx_query")
            {
                $this->sphinx_query = $this->toolkit->sqlEscape($v);
            }
            elseif ($k == "sphinx_mode")
            {
                if (in_array($v, array("all", "any", "phrase", "boolean", "extended")))
                {
                    $this->sphinx_args[] = "mode=" . $v;
                }
            }
            elseif ($k == "sphinx_offset")
            {
                if (is_numeric($v))
                {
                    $this->sphinx_args[] = "offset=" . $v;
                }
            }
            elseif ($k == "sphinx_limit")
            {
                if (is_numeric($v))
                {
                    $this->sphinx_args[] = "limit=" . $v;
                }
            }
            elseif (strpos($k, "sphinx_filter_") === 0)
            {
                $this->sphinx_args[] = "filter=" . str_replace("sphinx_filter_", "", $k) . "," . $v;
            }
            elseif (strpos($k, "sphinx_range_") === 0)
            {
                if (is_array($v) && (isset($v['max']) || isset($v['min'])))
                {
                    $max = isset($v['max']) ? intval($v['max']) : 1000000;
                    $min = isset($v['min']) ? intval($v['min']) : 0;
                    $this->sphinx_args[] = "range=" . str_replace("sphinx_range_", "", $k) . "," . $min . "," . $max;
                }
            }
            elseif (strpos($k, "sphinx_order_") === 0)
            {
                if (in_array(strtolower($v), array("asc", "desc")))
                {
                    $this->sphinx_order[] = str_replace("sphinx_order_", "", $k) . " " . $v;
                }
            }
        }

    }

    protected function buildQuery()
    {
    }

}