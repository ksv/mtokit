<?php
class mtoDfsAnonymousFilter
{

    function filter($filename)
    {
        if (strpos($filename, "/anonymous/") !== false)
        {
            return false;
        }
        return true;
    }
}