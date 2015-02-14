<?php
mtoClass :: import("mtokit/net/mtoUri.class.php");
interface mtoRouteResolver
{
    function accept(mtoUri $uri);

    function dispatch(mtoUri $uri, mtoHttpRequest $request);
}