<?php
class mtoActiveObjectCollection implements Iterator, Countable, ArrayAccess
{
    protected $dataset;
    protected $iteratedDataset;
    protected $limit;
    protected $offset;
    protected $count;
    protected $current;
    protected $valid = false;
    protected $sort_order;
    protected $sort_direction;
    protected $sort_method;

    function __construct($dataset = array())
    {
        $this->dataset = $dataset;
        if (is_array($dataset))
        {
            $this->setCount(count($dataset));
        }
    }


    function sort($field, $direction = "ASC", $method = "str")
    {
        $this->sort_order = $field;
        $this->sort_direction = $direction;
        $this->sort_method = $method;
        usort($this->dataset, array($this, "compareItems"));
        $this->iteratedDataset = null;
        return $this;
    }
    
    function getArray()
    {
        return $this->dataset;
    }
    
    function at($pos) 
    {
        return $this->dataset[$pos];
    }

    function rewind()
    {
        $this->_setupIteratedDataset();

        $values = reset($this->iteratedDataset);

        $this->current = $values;
        $this->key = key($this->iteratedDataset);
        $this->valid = is_array($values) || is_object($values);
    }

    function next()
    {
        $this->_setupIteratedDataset();

        $values = next($this->iteratedDataset);
        $this->current = $values;
        $this->key = key($this->iteratedDataset);
        $this->valid = is_array($values) || is_object($values);
    }

    function valid()
    {
        return $this->valid;
    }

    function current()
    {
        return $this->current;
    }

    function key()
    {
        return $this->key;
    }

    function paginate($offset, $limit)
    {
        $this->iteratedDataset = null;
        $this->offset = $offset;
        $this->limit = $limit;
        return $this;
    }


    function getOffset()
    {
        return $this->offset;
    }

    function getLimit()
    {
        return $this->limit;
    }


    function add($item)
    {
        $this->dataset[] = $item;
        $this->count++;
        $this->iteratedDataset = null;
    }

    function removeByField($field, $value)
    {
        foreach ($this->dataset as $key => $entry)
        {
            if (isset($entry[$field]) && $entry[$field] == $value)
            {
                $item = $this->dataset[$key];
                unset($this->dataset[$key]);
                $this->iteratedDataset = null;
                $this->count--;
                return $item;
            }
        }
    }

    function isEmpty()
    {
        return count($this->dataset) == 0;
    }

    //Countable interface
    function count()
    {
        return $this->count;
    }
    
    //Countable interface
    function getCount()
    {
        return $this->count;
    }


    //end

    function countPaginated()
    {
        return count($this->dataset);
    }


    //ArrayAccess interface
    function offsetExists($offset)
    {
        return !is_null($this->dataset[$offset]);
    }

    function offsetGet($offset)
    {
        return $this->dataset[$offset];
    }

    function offsetSet($offset, $value)
    {
        return $this->dataset[$offset] = $value;
    }

    function offsetUnset($offset)
    {
        unset($this->dataset[$offset]);
    }
    //end


    protected function _setupIteratedDataset()
    {
        if(!is_null($this->iteratedDataset))
        {
            return;
        }

        $this->iteratedDataset = $this->dataset;

        /*
        if(!$this->limit)
        {
            $this->iteratedDataset = $this->dataset;
            return;
        }

        if($this->offset < 0 || $this->offset >= count($this->dataset))
        {
            $this->iteratedDataset = array();
            return;
        }

        $to_splice_array = $this->dataset;
        $this->iteratedDataset = array_splice($to_splice_array, $this->offset, $this->limit);

        if(!$this->iteratedDataset)
        {
            $this->iteratedDataset = array();
        }
         *
         */
        
    }

    function setCount($count)
    {
        $this->count = $count;
    }

    function getPages()
    {
        if (/*empty($this->offset) || */ empty($this->limit))
        {
            return 0;
        }
        if( $this->count >0 )
        {
            return ceil($this->count/$this->limit);

        }
        else
        {
            return 0;
        }

    }

    private function compareItems($a, $b)
    {
        $field = $this->sort_order;
        switch ($this->sort_method)
        {
            case "int":
                if ($a[$field] == $b[$field])
                {
                    $result = 0;
                }
                else
                {
                    if ($a[$field] < $b[$field])
                    {
                        $result = -1;
                    }
                    else
                    {
                        $result = 1;
                    }
                }
            break;
            default:
                $result = strcmp($a, $b);
            break;
        }
        if ($this->sort_direction == "DESC")
        {
            $result = -$result;
        }
        return $result;
    }


    
}
