<?php
mtoClass :: import("mtokit/webapp/routes/mtoRouteResolver.interface.php");

class mtoCtlActIdRoute implements mtoRouteResolver
{
    function accept(mtoUri $uri)
    {
        $parts = explode("/", $uri->getPath());
        array_shift($parts);
        if (count($parts) == 3 && is_numeric($parts[2]))
        {
            return true;
        }
        return false;
    }

    function dispatch(mtoUri $uri, mtoHttpRequest $request)
    {
        $parts = explode("/", $uri->getPath());
        array_shift($parts);
        $request->set("controller", $parts[0]);
        $request->set("action", $parts[1]);
        $request->set("id", intval($parts[2]));
    }
}