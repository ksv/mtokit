<?php

class mtoBacktrace
{
    const EXPORT_DEPTH = 7;

    protected $backtrace = array();

    function __construct($limit_or_backtrace = null, $limit_or_offset = null, $offset = 0)
    {

        if (is_array($limit_or_backtrace))
        {
            $this->backtrace = $limit_or_backtrace;
            $limit = $limit_or_offset;
        }
        else
        {
            $this->backtrace = debug_backtrace();
            $limit = $limit_or_backtrace;
            $offset = (int) $limit_or_offset + 1;
        }

        if (is_null($limit))
        {
            $limit = count($this->backtrace) - $offset;
        }

        $this->backtrace = array_splice($this->backtrace, $offset, $limit);
    }

    function get()
    {
        return $this->backtrace;
    }

    function getContext()
    {
        return (sizeof($this->backtrace)) ? $this->backtrace[0] : '';
    }

    function toString()
    {
        $trace_string = '';

        foreach ($this->backtrace as $item)
        {
            $trace_string .= '* ';
            $trace_string .= $this->_formatBacktraceItem($item) . "\n";
        }
        return $trace_string;
    }

    function _formatBacktraceItem($item)
    {
        $trace_string = '';

        if (isset($item['class']))
        {
            $trace_string .= $item['class'];
            $trace_string .= "::";
        }

        if (isset($item['function']))
        {
            $trace_string .= $item['function'];
            $trace_string .= "(";
        }

        if (isset($item['args']))
        {
            $sep = '';
            foreach ($item['args'] as $arg)
            {
                $trace_string .= $sep;
                $sep = ', ';

                $trace_string .= self :: var_export($arg, 7);
            }
        }

        if (isset($item['function']))
        {
            $trace_string .= ")";
        }

        if (isset($item['file']))
        {
            $trace_string .= ' in "' . $item['file'] . '"';
            $trace_string .= " line ";
            $trace_string .= $item['line'];
        }

        return $trace_string;
    }

    static function create($limit = null, $offset = null, $backtrace = null)
    {
        return new self($backtrace, $limit, $offset);
    }

    static function var_export($arg, $level = 1)
    {
        $prefix = str_repeat('  ', ($level > 0) ? ($level - 1) : 0);
        switch (gettype($arg))
        {
            case 'NULL':
                return 'NULL';

            case 'boolean':
                return $arg ? 'TRUE' : 'FALSE';

            case 'integer':
                return 'INT(' . $arg . ')';

            case 'double':
                return 'FLOAT(' . $arg . ')';

            case 'resource':
                if (is_resource($arg))
                {
                    $resource_id = strstr((string) $arg, '#');
                    $resource_type = get_resource_type($arg);
                    return "RESOURCE($resource_id) of type (" . get_resource_type($arg) . ")";
                }
                else
                    return self :: var_export((string) $arg);

            case 'object':

                if (self :: EXPORT_DEPTH == $level)
                {
                    return 'OBJECT(' . get_class($arg) . ')';
                }

                $dump = 'OBJECT(' . get_class($arg) . ") {";
                if (get_object_vars($arg))
                {
                    $dump .= PHP_EOL;
                    foreach (get_object_vars($arg) as $name => $value)
                    {
                        $dump .= $prefix . "  [\"$name\"]=> "
                                . self :: var_export($value, $level + 1)
                                . PHP_EOL;
                    }
                    $dump .= $prefix;
                }
                $dump .= "}";
                return $dump;

            case 'array':

                if ($level == self :: EXPORT_DEPTH)
                {
                    return 'ARRAY(' . sizeof($arg) . ')';
                }
                else
                {
                    $dump = "ARRAY(" . sizeof($arg) . ') [';
                    if (sizeof($arg))
                    {
                        $dump .= PHP_EOL;
                        foreach ($arg as $arr_key => $arr_value)
                        {
                            $dump .= $prefix . "  [$arr_key] => " . self :: var_export($arr_value, $level + 1) . PHP_EOL;
                        }
                        $dump .= $prefix;
                    }
                    $dump .= "]";
                    return $dump;
                }

            case 'string':
                $dump = 'STRING(' . strlen($arg) . ') "';
                $dump .= self :: escape_string((string) $arg, 100);

                if (strlen($arg) > 100)
                {
                    $dump .= '...';
                }

                $dump .= '"';
                return $dump;

            default:
                return var_export($arg, true);
        }
    }

    static function escape_string($string, $length_limit = 100)
    {
        if ('cli' == php_sapi_name())
        {
            return substr($string, 0, $length_limit);
        }
        else
        {
            return htmlspecialchars(substr($string, 0, $length_limit));
        }
    }

}

