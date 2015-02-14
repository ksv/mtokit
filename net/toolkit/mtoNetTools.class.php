<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');
mtoClass :: import("mtokit/net/mtoHttpRequest.class.php");
mtoClass :: import("mtokit/net/mtoHttpSession.class.php");
mtoClass :: import("mtokit/net/mtoHttpResponse.class.php");

class mtoNetTools extends mtoAbstractTools
{
    protected $request = null;
    protected $session = null;
    protected $response = null;

    function getResponse()
    {
        if (is_null($this->response))
        {
            $this->response = mtoHttpResponse :: create();
        }
        return $this->response;
    }

    function getRequest()
    {
        if (is_null($this->request))
        {
            $this->request = mtoHttpRequest :: create();
        }
        return $this->request;
    }
    
    function getSession()
    {
        if (is_null($this->session))
        {
            $this->session = mtoHttpSession :: create();
        }
        return $this->session;
    }

}