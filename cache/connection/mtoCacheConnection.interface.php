<?php

interface mtoCacheConnection
{
    function add($key, $value, $ttl = false);

    function set($key, $value, $ttl = false);

    function replace($key, $value, $ttl = false);

    function get($key);

    function increment($key, $value=1);

    function delete($key);

    function flush();

    function getType();

    function status();

    function dumpKeys($args = array());
}
