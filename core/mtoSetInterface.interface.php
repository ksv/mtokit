<?php

interface mtoSetInterface extends ArrayAccess
{

    function get($name, $default = null);

    function set($name, $value);

    function remove($name);

    function reset();

    function export();

    function import($values);

    function has($name);
}

