<?php
class mtoRouteDispatcher
{
    private $resolvers = array();


    function addResolver(mtoRouteResolver $resolver)
    {
        array_push($this->resolvers, $resolver);
    }

    function dispatch(mtoHttpRequest $request)
    {
        if (empty($this->resolvers))
        {
            throw new mtoException("No route resolvers defined");
        }
        foreach ($this->resolvers as $resolver)
        {
            if ($resolver->accept($request->getUri()))
            {
                $resolver->dispatch($request->getUri(), $request);
                return;
            }
        }
        throw new mtoException("Unable to dispatch route");
    }

}