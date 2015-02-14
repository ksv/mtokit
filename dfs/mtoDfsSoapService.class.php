<?php

require_once("mtokit/soap/mtoSoapService.class.php");
require_once("mtokit/dfs/mtoDfsManager.class.php");
require_once("mtokit/profiler/mtoProfiler.class.php");

class mtoDfsSoapService extends mtoSoapService
{

    /**
     *
     * @param array $args
     * @return array
     */
    function registerClient($args)
    {
        try
        {
            $result = mtoDfsManager :: create()->registerClient($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "Client registered");
    }

    /**
     *
     * @param array $args
     * @return array
     */
    function getClientQueue($args)
    {
        try
        {
            $result = mtoDfsManager :: create()->getClientQueue($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "Queue received");
    }

    /**
     *
     * @param array $args
     * @return array
     */
    function setLastEvent($args)
    {
        try
        {
            $result = mtoDfsManager :: create()->setLastEvent($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "Event recorded");
    }



    /**
     *
     * @param array $args
     * @return array
     */
    function setLastSync($args)
    {
        try
        {
            $result = mtoDfsManager :: create()->setLastSync($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "Event recorded");
    }

    /**
     *
     * @param array $args
     * @return array
     */
    function getFileList($args)
    {
        try
        {
            $result = mtoDfsManager :: create()->getFileList($args);
        }
        catch (mtoSoapException $e)
        {
            //return $this->fail($e->getMessage());
        }
        $this->log(count($result['files']) . " files received");

        return $this->done($result, "Files received");
    }

    /**
     *
     * @param array $args
     * @return array
     */
    function getFile($args)
    {
        try
        {
            $result = mtoDfsManager :: create()->getFile($args);
        }
        catch (mtoSoapException $e)
        {
            var_dump($args['filename']);
            var_dump($e->getMessage());
            //return $this->fail($e->getMessage());
            $result = "";
        }
        return $this->done($result, "File received");
    }
    
    /**
     *
     * @param array $args
     * @return array
     */
    function getPingResult($args)
    {
        $this->done(array('content' => "OK"), "ping received");
    }

    /**
     *
     * @access private
     */
    private function log($message)
    {
        if (mtoProfiler :: instance()->getLogLevel() > mtoProfiler :: LOG_LEVEL_NOTICE)
        {
            mtoProfiler :: instance()->logDebug($message, "dfs");
        }
    }

}