<?php
mtoClass :: import("mtokit/webapp/routes/mtoRouteDispatcher.class.php");

class mtoRequestDispatchingFilter implements mtoInterceptingFilter
{
    function run($filter_chain)
    {
        $toolkit = mtoToolkit :: instance();
        $dispatcher = new mtoRouteDispatcher();
        $routes = mtoConf :: instance()->get("webapp", "routes");
        foreach (explode(",", $routes) as $route)
        {
            $class = "mto" . mto_camel_case($route) . "Route";
            mtoClass :: import("mtokit/webapp/routes/" . $class . ".class.php");
            $dispatcher->addResolver(new $class);
        }
        $dispatcher->dispatch($toolkit->getRequest());

        $filter_chain->next();
    }
}