<?php
class mtoProfilerLogger extends mtoProfilerTool
{

    function log($message, $args = array())
    {
        $request = $this->profiler->getRequest();
        if (!isset($args['file']))
        {
            $file = "common";
        }
        else
        {
            $file = $args['file'];
        }
        $file .= ".log";
        if (isset($args['level']))
        {
            if ($this->profiler->getLogLevel() < $args['level'])
            {
                return;
            }
        }
        $msg = array();
        $msg[] = date("Y-m-d H:i:s");
        if (!isset($args['local']) && is_object($request))
        {
            $msg[] = $request->getServer('REMOTE_ADDR');
        }
        if (!isset($args['nouser']))
        {
            $uid = $this->profiler->getUid();
            
            if (isset($args['extract_user']))
            {
                $user = $this->profiler->getUser();
                $msg[] = $uid > 0 ? $user->getLoggingIdentifier() : "none";
            }
            else
            {
                $msg[] = $uid;
            }
        }
        foreach ($args as $k => $v)
        {
            if (strpos($k, "log_") === 0)
            {
                $msg[] = $v;
            }
        }
        $msg[] = $message;
        if (!file_exists($this->profiler->getLogFolder()))
        {
            mtoFs :: mkdir($this->profiler->getLogFolder());
        }
        if (!file_exists(dirname($this->profiler->getLogFolder() . "/" . $file)))
        {
            mtoFs :: mkdir(dirname($this->profiler->getLogFolder() . "/" . $file));
        }
        error_log(implode("\t", $msg)."\n", 3, $this->profiler->getLogFolder() . "/" . $file);
    }
}