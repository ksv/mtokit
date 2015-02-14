<?php
mtoClass :: import("mtokit/api/exceptions/mtoApiException.class.php");
mtoClass :: import("mtokit/net/mtoRemoteRequest.class.php");
class mtoApi
{
    protected $transport;
    protected $classmap;
    protected $conf;
    protected $version;
    protected $service;
    protected $method;
    protected $request_method;
    protected $object_id;
    protected $params;
    protected $body;
    protected $avail_transport = array("json", "plain");
    
    const ERR_UNKNOWN_TRANSPORT = -1;
    const ERR_UNKNOWN_VERSION = -2;
    const ERR_UNKNOWN_SERVICE = -3;
    const ERR_UNKNOWN_METHOD = -4;
    const ERR_NOT_AUTHORIZED = -5;

    function __construct()
    {
        $this->conf = mtoConf :: instance();
        $this->classmap = $this->conf->getSection("api_classmap");
    }
    
    function route(mtoHttpRequest $request)
    {
        $params  = $this->selfRoute($request);
        $this->params = $request->dump();
        $body = file_get_contents("php://input");
        $this->createTransport($params['transport']);
        if (!is_numeric($params['version']))
        {
            throw new mtoApiException("Version is invalid", array('version' => $params['version']), self :: ERR_UNKNOWN_VERSION);
        }
        if (!empty($body))
        {
            $this->body = $this->transport->decode($body);
        }
        $this->version = $params['version'];
        if (empty($params['service']))
        {
            throw new mtoApiException("Service is invalid", array(), self :: ERR_UNKNOWN_SERVICE);
        }
        $this->createService($params['service'], $params['version']);
        //var_dump($this->service);
        if (empty($params['method']) || !$this->service->hasMethod($params['method']))
        {
            throw new mtoApiException("Method is unknown", array('method' => $params['method']), self :: ERR_UNKNOWN_METHOD);
        }
        $this->method = $params['method'];
        $this->object_id = !empty($params['id']) ? $params['id'] : 0;
        return $this;
    }
    
    function execute()
    {
        mtoProfiler :: instance()->logDebug("RECEIVED: " . get_class($this->service) . "::" . $this->method . "(" . get_class($this->transport) . ")\t" . json_encode($this->params), "api");
        $this->service->authorize();
        $result = $this->transport->encode($this->service->call());
        mtoProfiler :: instance()->logDebug("RESULT: " . get_class($this->service) . "::" . $this->method . "(" . get_class($this->transport) . ")\t" . $result, "api");
        return $result;
    }
    
    function outError(mtoApiException $e)
    {
        return $this->transport->encode(array_merge($e->getParams(), array(
            'error_code' => $e->getCode(),
            'error_message' => $e->getOriginalMessage()
        )));
    }
    
    private function selfRoute(mtoHttpRequest $request)
    {
        $params = array();
        $params['request_method'] = $request->getMethod();
        $els = $request->getUri()->getPathElements();
        array_shift($els);
        array_shift($els);
        $params['version'] = array_shift($els);
        $params['transport'] = array_shift($els);
        $params['service'] = array_shift($els);
        $params['method'] = array_shift($els);
        if (count($els))
        {
            $params['id'] = array_shift($els);
        }
        return $params;
    }
    
    private function createTransport($t)
    {
        if (!in_array($t, $this->avail_transport))
        {
            $t = "plain";
        }
        $class = "mtoApiTransport" . mto_camel_case($t);
        mtoClass :: import("mtokit/api/transport/" . $class . ".class.php");
        $this->transport = new $class();
        if (!in_array($t, $this->avail_transport))
        {
            throw new mtoApiException("Transport is invalid", array(), self :: ERR_UNKNOWN_TRANSPORT);
        }
    }
    
    private function createService($s, $v)
    {
        $root = $this->conf->get("core", "root");
        if (!isset($this->classmap[$s]))
        {
            throw new mtoApiException("Service is invalid:" . $s . print_r($this->classmap, true), array('service' => $s), self :: ERR_UNKNOWN_SERVICE);
        }
        $class = basename($this->classmap[$s]);
        $path = dirname($this->classmap[$s]);
        if (file_exists($path . "/" . $v . "/" . $class . ".class.php"))
        {
            mtoClass :: import($path . "/" . $v . "/" . $class . ".class.php");
        }
        else
        {
            mtoClass :: import($path . "/" . $class . ".class.php");
        }
        $this->service = new $class($this);
    }
    
    function __call($method, $args = array())
    {
        if (strpos($method, "get") === 0)
        {
            $prop = mto_under_scores(substr($method, 3));
            if (property_exists($this, $prop))
            {
                return $this->$prop;
            }
            else
            {
                throw new mtoException("Unknown propery: " . $prop);
            }
        }
        throw new mtoException("Unknown method: " . $method);
    }
    
    function getParam($param)
    {
        return isset($this->params[$param]) ? $this->params[$param] : null;
    }
    
    function callService($uri, $params = array())
    {
        $this->createTransport($params['transport']);
        $info = array();
        if (!empty($params['body']))
        {
            $info['post'] = 1;
            $info['postfields'] = $this->transport->encode($params['body']);
        }
        if (!empty($params['http_method']))
        {
            $info['customrequest'] = $params['http_method'];
        }
        mtoProfiler :: instance()->logDebug("CALL: " . $uri, "api");
        try
        {
            $resp = mtoRemoteRequest :: fetchCurl($uri, array(), $info);
        }
        catch (mtoException $e)
        {
            mtoProfiler :: instance()->logDebug("CALL FAILED: " . $uri, "api");
            return array();
        }
        return $this->transport->decode($resp);
    }
    
    static function call($uri, $args = array(), $params = array())
    {
        if (empty($params['host']))
        {
            $params['host'] = mtoConf :: instance()->val("core|domain");
        }
        if (empty($params['version']))
        {
            $params['version'] = "0.0";
        }
        if (empty($params['transport']))
        {
            $params['transport'] = "json";
        }
        $endpoint = "http://" . $params['host'] . "/api/" . $params['version'] . "/" . $params['transport'] . $uri;
        if (!empty($args))
        {
            $endpoint .= "?" . http_build_query($args);
        }
        $api = new self();
        return $api->callService($endpoint, $params);
    }
    
    static function callJson($uri, $args = array(), $params = array())
    {
        $params['transport'] = "json";
        return self :: call($uri, $args, $params);
    }
}