<?php
mtoClass :: import("mtokit/webapp/routes/mtoRouteResolver.interface.php");

class mtoAnyRoute implements mtoRouteResolver
{
    function accept(mtoUri $uri)
    {
        return true;
    }

    function dispatch(mtoUri $uri, mtoHttpRequest $request)
    {
    }
}