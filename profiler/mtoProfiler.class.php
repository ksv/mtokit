<?php
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
mtoClass :: import("mtokit/profiler/mtoProfilerTool.class.php");
mtoClass :: import("mtokit/profiler/mtoProfilerTimer.class.php");
mtoClass :: import("mtokit/profiler/mtoProfilerParser.class.php");
mtoClass :: import("mtokit/profiler/mtoProfilerLogger.class.php");
mtoClass :: import("mtokit/profiler/mtoProfilerQueryProfiler.class.php");
mtoClass :: import("mtokit/profiler/mtoProfilerSoapService.class.php");
mtoClass :: import("mtokit/profiler/lib/FirePHP.class.php");

class mtoProfiler
{
    const LOG_LEVEL_NONE = 0;
    const LOG_LEVEL_FATAL = 1;
    const LOG_LEVEL_ERROR = 2;
    const LOG_LEVEL_WARN = 3;
    const LOG_LEVEL_NOTICE = 4;
    const LOG_LEVEL_DEBUG = 5;
    const LOG_LEVEL_ALWAYS = 10;

    private $timer = null;
    private $qp = null;
    private $parser = null;
    private $logger = null;
    private $request = null;
    private $user = null;
    private $uid = 0;
    private $log_level = self :: LOG_LEVEL_ERROR;
    private $log_folder = null;
    private $config;
    private $js;
    private $console = null;
    
    use mtoSingletone;

    function __construct($args = array())
    {
        $this->config = mtoConf :: instance()->getSection("profiler");
        $this->log_folder = $this->config['log_folder'];
        if (empty($this->config['log_level']))
        {
            $this->config['log_level'] = "LOG_LEVEL_DEBUG";
        }
        $this->log_level = constant("self::" . $this->config['log_level']);
        if (isset($args['request']))
        {
            $this->request = $args['request'];
        }
        if (isset($args['user']))
        {
            $this->user = $args['user'];
        }
        if (isset($args['log_level']))
        {
            $this->log_level = $args['log_level'];
        }
        if (isset($args['log_folder']))
        {
            $this->log_folder = $args['log_folder'];
        }
        if (extension_loaded("pinba"))
        {
            if (!defined("IN_CLI"))
            {
                if (isset($_SERVER['REQUEST_URI']))
                {
                    pinba_script_name_set($this->preparePinbaRequest($_SERVER['REQUEST_URI']));
                }
            }
        }
        //$this->js = FirePHP :: getInstance(true);
        if (!empty($this->config['console_enable']))
        {
            $this->initConsole();
        }
    }
    
    
    function jsdump($var)
    {
        $this->js->log($var);
    }
    
    function jstrace()
    {
        $this->js->trace("Stack trace");
    }
    
    function logConsole($var, $tags = "")
    {
        if (!empty($this->config['console_enable']))
        {
            $this->console->debug($var, $tags);
        }
    }
    

    function logMessage($message, $level = self :: LOG_LEVEL_WARN, $logfile = null, $args = array())
    {
        if (!is_null($logfile))
        {
            $args['file'] = $logfile;
        }
        $args['level'] = $level;
        $this->getLogger()->log($message, $args);
        return $this;
    }

    function logFatal($message, $logfile = null)
    {
        $this->logMessage($message, self :: LOG_LEVEL_FATAL, $logfile);
        return $this;
    }

    function logError($message, $logfile = null)
    {
        $this->logMessage($message, self :: LOG_LEVEL_ERROR, $logfile);
        return $this;
    }

    function logWarn($message, $logfile = null)
    {
        $this->logMessage($message, self :: LOG_LEVEL_WARN, $logfile);
        return $this;
    }

    function logNotice($message, $logfile = null)
    {
        $this->logMessage($message, self :: LOG_LEVEL_NOTICE, $logfile);
        return $this;
    }

    function logDebug($message, $logfile = null)
    {
        $this->logMessage($message, self :: LOG_LEVEL_DEBUG, $logfile);
        return $this;
    }

    function logCatchError($message)
    {
        if (isset($_SERVER['HTTP_HOST']))
        {
            $message .= "\t" . $_SERVER['HTTP_HOST'];
        }
        if (isset($_SERVER['REQUEST_URI']))
        {
            $message .= "\t" . $_SERVER['REQUEST_URI'];
        }
        if (isset($_SERVER['HTTP_REFERER']))
        {
            $message .= "\t" . $_SERVER['HTTP_REFERER'];
        }
        $this->logDebug($message, "debug/catch_error");
    }

    function getRequest()
    {
        return $this->request;
    }

    function setRequest($request = null)
    {
        if (is_null($request))
        {
            $request = mtoToolkit :: instance()->getRequest();
        }
        $this->request = $request;
        return $this;
    }

    function getUser()
    {
        return $this->user;
    }

    function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    function getUid()
    {
        return $this->uid;
    }

    function setUid($uid)
    {
        $this->uid = $uid;
        return $this;
    }
    
    function getLogLevel()
    {
        return $this->log_level;
    }

    function setLogLevel($log_level)
    {
        $this->log_level = $log_level;
        return $this;
    }

    function getLogFolder()
    {
        return $this->log_folder;
    }

    function setLogFolder($log_folder)
    {
        $this->log_folder = $log_folder;
        return $this;
    }

    function timerStart($name)
    {
        $this->getTimer()->start_checkpoint($name);
        return $this;
    }

    function timerStartInc($name)
    {
        $this->getTimer()->start_increment_checkpoint($name);
        return $this;
    }

    function timerStartCount($name)
    {
        $this->getTimer()->start_count_checkpoint($name);
        return $this;
    }

    function timerStop($name)
    {
        $this->getTimer()->end_any_checkpoint($name);
        return $this;
    }
    
    function timerStartPinba($name, $tags)
    {
        $this->getTimer()->start_pinba_checkpoint($name, $tags);
        return $this;
    }
    
    function timerStopPinba($name)
    {
        $this->getTimer()->end_pinba_checkpoint($name);
        return $this;
    }
    
    function timerGetAll()
    {
        return $this->getTimer()->get_all_checkpoints();
    }

    function timerTrace($message, $init = null)
    {
        $this->getTimer()->trace($message, $init);
    }

    function timerTraceGet()
    {
        return $this->getTimer()->get_trace();
    }

    function getLogger()
    {
        if (is_null($this->logger))
        {
            $this->logger = new mtoProfilerLogger($this);
        }
        return $this->logger;
    }

    function getTimer()
    {
        if (is_null($this->timer))
        {
            $this->timer = new mtoProfilerTimer($this);
        }
        return $this->timer;
    }

    function getParser()
    {
        if (is_null($this->parser))
        {
            $this->parser = new mtoProfilerParser($this);
        }
        return $this->parser;
    }

    function getQueryProfiler()
    {
        if (is_null($this->qp))
        {
            $this->qp = new mtoProfilerQueryProfiler($this);
        }
        return $this->qp;
    }

    function pushWsdl()
    {
        header("Content-type: text/xml");
        echo mtoSoapService :: getWsdl("profiler.wsdl");
        die();
    }

    function handleSoapRequest()
    {
        $server = new SoapServer(mtoConf :: instance()->getFile("soap", "wsdl_path") . '/profiler.wsdl');
        $server->setClass("mtoProfilerSoapService");
        $server->handle();
    }
    
    function sendNotify($message, $number = "", $use_locks = true)
    {
        $mails = explode(";", mtoConf :: instance()->get("profiler", "notify_email"));
        foreach ($mails as $mail)
        {
            if (!empty($mail))
            {
                mtoToolkit :: instance()->send_plain_mail($mail, $message, $message);
            }
        }
        if ($number == "-")
        {
            return;
        }
        $lockdir = mtoConf :: instance()->get("core", "vardir") . "/locks";
        if ($use_locks)
        {
            if (file_exists($lock_dir . "/sms.lock"))
            {
                return;
            }
        }
        if (!empty($number) && strpos($number, "++") === 0)
        {
            $numbers = array();
            $number = str_replace("++", "+", $number);
        }
        else
        {
            $numbers = explode(";", mtoConf :: instance()->get("profiler", "notify_sms"));
        }
        if (!empty($number))
        {
            $numbers[] = $number;
        }
        foreach ($numbers as $num)
        {
            if (!empty($num))
            {
                mtoToolkit :: instance()->smsSend($num, $message, "GENERATOR");
            }
        }
        if ($use_locks)
        {
            file_put_contents($lockdir . "/sms.lock", "1");
        }
    }
    
    function unlockSms()
    {
        $lockdir = mtoConf :: instance()->get("core", "vardir") . "/locks";
        if (file_exists($lockdir . "/sms.lock"))
        {
            unlink($lockdir . "/sms.lock");
        }
    }
    
    function preparePinbaRequest($uri)
    {
        $parts = array();
        if (defined("IN_GENERATOR"))
        {
            $parts[] = "cachegen";
        }
        else
        {
            if (isset($_REQUEST['mode']))
            {
                $parts[] = $_REQUEST['mode'];
            }
            if (isset($_REQUEST['action']))
            {
                $parts[] = $_REQUEST['action'];
            }
        }
        return "/" . implode("/", $parts);
    }

    function initConsole()
    {
        spl_autoload_register(function ($class) {
                if(strpos($class, "PhpConsole") === 0) {
                        require_once(__DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php');
                }
        });
        $connector = PhpConsole\Connector::getInstance();
        if (!empty($this->config['console_password']))
        {
            $connector->setPassword($this->config['console_password'], true);
        }
        if (!empty($this->config['console_ip']))
        {
            $connector->setAllowedIpMasks(explode(",", $this->config['console_ip']));    
        }
        $this->console = PhpConsole\Handler::getInstance();
        if (empty($this->config['console_catch_errors']))
        {
            $this->console->setHandleErrors(false);  
            $this->console->setHandleExceptions(false); 
            $this->console->setCallOldHandlers(false);            
        }
        $this->console->start();

    }
    

}