<?php
trait mtoSingletone
{
    private static $instance = null;
    
    public static function instance($args = array())
    {
        if (is_null(self :: $instance))
        {
            self :: $instance = new self($args);
        }
        return self :: $instance;
    }
    
    public static function create()
    {
        return self :: instance();
    }
}