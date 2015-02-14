<?php
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
class mtoHttpSession
{
    use mtoSingletone;
    
    
    function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    function has($key)
    {
        return isset($_SESSION[$key]);
    }

    function delete($key)
    {
        unset($_SESSION[$key]);
    }
    
    function getInteger($key)
    {
        return intval($this->get($key));
    }
    
    function start()
    {
        session_start();
    }
    
    function clear()
    {
        $_SESSION = "";
    }

    function destroy()
    {
        session_destroy();
    }
}