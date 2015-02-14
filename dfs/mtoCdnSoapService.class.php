<?php

mtoClass :: import("mtokit/soap/mtoSoapService.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");
mtoClass :: import("mtokit/profiler/mtoProfiler.class.php");

class mtoCdnSoapService extends mtoSoapService
{

    /**
     *
     * @param array $args
     * @return array
     */
    function checkFile($args)
    {
        try
        {
            $result = mtoCdnManager :: create()->checkFile($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "File checked");
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
            $result = mtoCdnManager :: create()->getFile($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "File received");
    }

    /**
     *
     * @param array $args
     * @return array
     */
    function deleteCache($args)
    {
        try
        {
            $result = mtoCdnManager :: create()->deleteCachedFile($args);
        }
        catch (mtoSoapException $e)
        {
            return $this->fail($e->getMessage());
        }
        return $this->done($result, "Cache removed");
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