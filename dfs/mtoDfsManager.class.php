<?php
mtoClass :: import("mtokit/soap/mtoSoapService.class.php");
mtoClass :: import("mtokit/config/mtoConf.class.php");
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
mtoClass :: import("mtokit/dfs/mtoDfsSoapService.class.php");
mtoClass :: import("mtokit/profiler/mtoProfiler.class.php");
mtoClass :: import("mtokit/soap/mtoSoapException.class.php");
mtoClass :: import("mtokit/api/mtoApi.class.php");
mtoClass :: import("mtokit/dfs/mtoCdnManager.class.php");
mtoClass :: import("mtokit/queue/mtoQueue.class.php");

class mtoDfsManager
{
    private $root;
    private $queue;
    private $client;
    private $toolkit;
    private $config;
    private $cdn;

    const ACTION_CREATE = 1;
    const ACTION_REPLACE = 2;
    const ACTION_DELETE = 3;
    
    use mtoSingletone;


    /**
     * Constructor
     */
    function __construct()
    {
        $this->config = mtoConf :: instance()->getSection("dfs");
        $this->root = $this->config['root'];
        $this->cdn = mtoCdnManager :: create("user");
        //ini_set("memory_limit", "2048M");
        set_time_limit(0);
    }


    function getRoot()
    {
        return $this->root;
    }
    
    function getMasterHost()
    {
        return $this->cdn->getHostById($this->config['master_host'], "ctl");
    }
    
    /**
     * local :: master
     * @param string $f
     */
    function put($f, $queued = false)
    {
        if ($this->config['is_master'])
        {
            if (is_file($f))
            {
                if (strpos($f, "/anonymous/") === false)
                {
                    if ($queued)
                    {
                        mtoQueue :: create("dfs")->createEvent("dfs_push", array('file' => $f));
                    }
                    else
                    {
                        $this->createEvent($this->_fn($f), self :: ACTION_REPLACE);
                        foreach ($this->cdn->getAllHosts() as $host_id => $host)
                        {
                            if ($host_id != $this->cdn->getOption("my_id"))
                            {
                                mtoApi :: callJson("/dfs/fetch", array('file' => $this->_fn($f), 'async' => 1), array('host' => $host['ctl']));
                            }
                        }
                    }
                    
                }
            }
        }
    }

    /**
     * local :: master
     * @param string $f
     */
    function delete($f)
    {
        if ($this->config['is_master'])
        {
            $this->createEvent($this->_fn($f), self :: ACTION_DELETE);
            if (strpos($f, "/anonymous/") === false)
            {
                foreach ($this->cdn->getAllHosts() as $host_id => $host)
                {
                    if ($host_id != $this->cdn->getOption("my_id"))
                    {
                        mtoApi :: callJson("/dfs/delete", array('file' => $this->_fn($f), 'async' => 1), array('host' => $host['ctl']));
                    }
                }
            }
        }
    }

    /**
     * system
     */
    function assert()
    {
        if (empty($this->client))
        {
            throw new mtoSoapException("Client not registered: " . $_SERVER['REMOTE_ADDR']);
        }
    }

    /**
     * system
     * @return bool
     */
    function isMaster()
    {
        return $this->config['is_master'];
    }

    /**
     * system
     * @return <bool
     */
    function isClient()
    {
        return $this->config['is_slave'];
    }

    /**
     * local :: client
     *
     * @param string $login
     * @param string $password
     * @return
     */
    function register($login, $password)
    {
        return mtoSoapService :: callService($this->config['master_wsdl'], "registerClient", array('login' => $login, 'password' => $password));
    }

    /**
     * local :: client
     * @return array
     */
    function queue()
    {
        if ($this->config['is_slave'])
        {
//            return mtoSoapService :: callService($this->config['master_wsdl'], "getClientQueue", array(
//                    'login' => $this->config['login'],
//                    'password' => $this->config['password']
//            ));
            return mtoApi :: callJson("/dfs/queue", array('login' => $this->config['login'], 'password' => $this->config['password']), array('host' => $this->cdn->getHostById($this->config['master_host'], 'ctl')));
        }
        else
        {
            return null;
        }
    }

    /**
     * local :: client
     * @return array
     */
    function record_last_event($lastevent)
    {
        if ($this->config['is_slave'])
        {
//            return mtoSoapService :: callService($this->config['master_wsdl'], "setLastEvent", array(
//                    'login' => $this->config['login'],
//                    'password' => $this->config['password'],
//                    'lastevent' => $lastevent
//            ));
            return mtoApi :: callJson("/dfs/record_event", array(
                'login' => $this->config['login'],
                'password' => $this->config['password'],
                'event_id' => $lastevent
            ), array(
                'host' => $this->cdn->getHostById($this->config['master_host'], 'ctl')
            ));
        }
        else
        {
            return null;
        }
    }

    /**
     * local :: client
     * @return array
     */
    function record_last_sync($time)
    {
        if ($this->config['is_slave'])
        {
//            return mtoSoapService :: callService($this->config['master_wsdl'], "setLastSync", array(
//                    'login' => $this->config['login'],
//                    'password' => $this->config['password'],
//                    'lastsync' => $time
//            ));
            return mtoApi :: callJson("/dfs/record_sync", array(
                'login' => $this->config['login'],
                'password' => $this->config['password'],
                'sync' => $time
            ), array(
                'host' => $this->cdn->getHostById($this->config['master_host'], 'ctl')
            ));
        }
    }




    /**
     * local :: client
     *
     * @param string $f
     * @return string
     */
    function get($f)
    {
        
        if (file_exists($this->root . "/" . $this->_fn($f)))
        {
            $this->log("EXISTS: " . $f);
            return $f;
        }
        if (!empty($this->config['is_slave']))
        {
            $result  = mtoSoapService :: callService($this->config['master_wsdl'], "getFile", array(
                    'login' => $this->config['login'],
                    'password' => $this->config['password'],
                    'filename' => $this->_fn($f)
            ));
            if (!isset($result['content']))
            {
                $this->log("NOT FOUND: " . $f);
                return false;
            }
            $filename = $this->root . '/' . $this->_fn($f);
            if (file_exists($filename))
            {
                unlink($filename);
            }
            mtoFs :: mkdir(dirname($filename));
            file_put_contents($filename, base64_decode($result['content']));
            $this->log("GET: " . $f);
            return $f;
        }
        else
        {
            $this->log("NOT FOUND: " . $f);
            return false;
        }
    }

    /**
     * local :: client
     */
    function files($all = 0, $empty = 0)
    {
        if ($this->config['is_slave'])
        {
            return mtoSoapService :: callService($this->config['master_wsdl'], "getFileList", array(
                  'login' => $this->config['login'],
                  'password' => $this->config['password'],
                  'all' => $all,
                  'empty' => $empty
            ));
        }
    }


    /**
     * local :: master
     */
    function clear()
    {
        if ($this->config['is_master'])
        {
            $row = mtoDb :: fetchOneRow("select min(last_event) as min_event from `".$this->config['client_table']."`");
            $min_event = isset($row['min_event']) ? $row['min_event'] : 0;
            mtoDb :: execute("delete from `".$this->config['queue_table']."` where id<".intval($min_event));
            $this->log("QUEUE: flushed at " . $min_event. " event");
            return array('status' => "done", "message" => "Queue cleaned");
        }
    }

    /**
     * local :: client
     * @param array $files
     */
    function process($files, $filters = array())
    {
        if (!$this->config['is_slave'])
        {
            return;
        }
        $last_id = 0;
        $fids = array();
        foreach ($files as $file)
        {
            if (!isset($fids[$file['filename']]))
            {
                $fids[$file['filename']] = array();
            }
            $fids[$file['filename']][] = $file['id'];
        }
        foreach ($files as $file)
        {
            $filename = $this->root . '/' . $this->_fn($this->root . '/' . $file['filename']);
            $skip = false;
            foreach ($filters as $filter)
            {
                if (!$filter->filter($filename))
                {
                    $skip = true;
                }
            }
            if ($skip)
            {
                $last_id = $file['id'];
                continue;
            }
            if ($file['action'] == self :: ACTION_DELETE)
            {
                if (file_exists($filename))
                {
                    unlink($filename);
                    $this->log("REMOVE:" . $filename);
                }
                else
                {
                    $this->log("ERROR: attempt to remove " . $filename);
                }
            }
            else
            {
                if (file_exists($filename))
                {
                    unlink($filename);
                    $this->log("UPDATE: " . $filename);
                }
                else
                {
                    $this->log("CREATE:" . $filename);
                }
                mtoFs :: mkdir(dirname($filename));
                if (!mtoFs :: rcp("http://" . $this->cdn->getHostById($this->config['master_host'], "ctl") . "/" . $file['filename'], $filename))
                {
                    var_dump("NOT FOUND(".date("Y-m-d H:i:s")."): " . $file['filename']);
                }
                else if (md5_file($filename) != $file['checksum'])
                {
                    if ($fids[$file['filename']] <= 1)
                    {
                        var_dump("CHECKSUM WRONG: " . $file['filename'] . "(id:".$file['id'].", received:".md5_file($filename).", remote:".$file['checksum'].")");
                    }
                }
                //var_dump($filename);
                //file_put_contents($filename, base64_decode($file['content']));
            }
            $last_id = $file['id'];
        }
        return array('last_id' => $last_id);
    }

    /**
     * local :: client
     * @param array $files
     */
    function check($files)
    {
        $remote = array();
        $local = array();
        foreach ($files as $file)
        {
            $remote[$file['filename']] = $file['checksum'];
        }
        clearstatcache();
        die("FIXME");
        $fs = lmbFs :: findRecursive($this->root, "df");
        foreach ($fs as $file)
        {
            if (!preg_match("#/\.svn/#", $file) && is_file($this->root. '/' . $this->_fn($file)))
            {
                $hash = md5(file_get_contents($this->root. '/' . $this->_fn($file)));
                $local[$this->_fn($file)] = $hash;
            }
        }

        $diff1 = array_diff_key($remote, $local);
        //$diff2 = array_diff_key($local, $remote);
        $diff3 = array();
        foreach ($remote as $k => $v)
        {
            if (isset($local[$k]) && $local[$k] != $v)
            {
                $diff3[] = $k;
            }
        }
        return array(
            'status' => count($diff1) == 0 && count($diff2) ? "done" : "fail",
            'message' => "DFS Compare completed",
            'absent_local' => $diff1,
            //'absent_remote' => $diff2,
            'changed' => $diff3
        );
    }

    /**
     * remote
     * @param <type> $args
     * @return <type>
     */
    function registerClient($args)
    {
        if (empty($args['login']) || empty($args['password']))
        {
            throw new mtoSoapException("Too few parameters");
        }
        $ip = ip2long($_SERVER['REMOTE_ADDR']);
        $row = mtoDb :: fetchOneRow("select * from `".$this->config['client_table']."` where ip='".intval($ip)."' and login='".$args['login']."'");
        if ($row && $row['login'])
        {
            throw new mtoSoapException("Client exists");
        }
        mtoDb :: execute("insert into `".$this->config['client_table']."` (ip, last_event, login, password) values ('".intval($ip)."', 0, '".$args['login']."', md5('".$args['password']."'))");
        return array();
    }

    /**
     * remote
     * @param <type> $args
     */
    function getClientQueue($args = array())
    {
        ini_set("memory_limit", "512M");
        if (empty($this->client))
        {
            $this->login($args['login'], $args['password']);
            $this->assert();
        }
        $this->log("QUEUE: " . $this->client['login'] . " update started");
        $files = mtoDb :: fetch("select * from `".$this->config['queue_table']."` where id>".intval($this->client['last_event'])." order by id limit 500");
        $last_id = 0;
        foreach ($files as $k => $file)
        {
            if (!file_exists($this->root . "/" . $file['filename']))
            {
                unset($files[$k]);
            }
//            if ($file['action'] !=self :: ACTION_DELETE)
//            {
//                if (file_exists($this->root . "/" . $file['filename']))
//                {
//                    //mtoProfiler :: instance()->logDebug($args['login'] . "[".number_format(memory_get_usage())."] :: " . number_format(filesize($this->root . "/" . $file['filename'])) . " - " . $file['filename'], "debug/dfs");
//                    $files[$k]['content'] = base64_encode(file_get_contents($this->root . "/" . $file['filename']));
//                }
//                else
//                {
//                    unset($files[$k]);
//                }
//            }
//            else
//            {
//                $files[$k]['content'] = "";
//            }
            $last_id = $file['id'];
        }
        if (count($files) <= 0 && $last_id > 0)
        {
            mtoDb :: execute("update `".$this->config['client_table']."` set last_event=".intval($last_id)." where id=" . $this->client['id']);
        }
        return array('files' => $files);
    }

    /**
     * remote
     * @param <type> $args
     */
    function setLastEvent($args)
    {
        if (empty($this->client))
        {
            $this->login($args['login'], $args['password']);
            $this->assert();
        }
        if (empty($args['lastevent']))
        {
            return array();
        }
        mtoDb :: execute("update `".$this->config['client_table']."` set last_event=".intval($args['lastevent']).", last_event_time=".time()." where id=" . intval($this->client['id']));
        $this->log("QUEUE: " . $this->client['login'] . " last_event=" . $args['lastevent'] . ", client: " . $this->client['id']);
        return array();
    }

    /**
     * remote
     * @param <type> $args
     */
    function setLastSync($args)
    {
        if (empty($this->client))
        {
            $this->login($args['login'], $args['password']);
            $this->assert();
        }
        mtoDb :: execute("update `".$this->config['client_table']."` set last_event=".intval($args['lastsync'])." where id=" . intval($this->client['id']));
        $this->log("SYNC: " . $this->client['login'] . " sync_time=" . date("Y-m-d H:i:s", $args['lastsync']));
        return array();
    }

    /**
     * remote
     * @param array $args
     */
    function getFileList($args)
    {
        die("FIXME");
        $this->login($args['login'], $args['password']);
        $this->assert();
        clearstatcache();
        $fs = lmbFs :: findRecursive($this->root, "df");
        $this->log(count($fs)." files scaned");
        $files = array();
        foreach ($fs as $file)
        {
            if (!preg_match("#/\.svn/#", $file) && is_file($this->root . '/' . $this->_fn($file)))
            {
                if ($args['all'] || (filemtime($file) > $this->client['last_sync']))
                {
                    $files[] = array('id' => 0, 'filename' => $this->_fn($file),'checksum' => md5(file_get_contents($this->root . '/' . $this->_fn($file))), 'content' => $args['empty'] ? "" : base64_encode(file_get_contents($this->root.'/'.$this->_fn($file))));
                }
            }
        }
        $this->log(count($files)." files filtered");
        return array('files' => $files);
    }

    /**
     *
     *  remote
     * @param array $args
     */
    function getFile($args)
    {
        $this->login($args['login'], $args['password']);
        $this->assert();
        if (file_exists($this->root . '/' . $this->_fn($this->root . '/' . $args['filename'])))
        {
            $this->log("GET: " . $args['filename']);
            return array('content' => base64_encode(file_get_contents($this->root . '/' . $this->_fn($this->root . "/" . $args['filename']))));
        }
        else
        {
            $this->log("NOT FOUND: " . $args['filename']);
            throw new mtoSoapException("File not found");
        }
    }


    /**
     * system
     * @param <type> $login
     * @param <type> $password
     */
    function login($login, $password)
    {
        $this->client = mtoDb :: fetchOneRow("select * from `".$this->config['client_table']."` where ip='".ip2long($_SERVER['REMOTE_ADDR'])."' and login='".$login."' and password=md5('".$password."')");
    }

    private function _fn($filename)
    {
        //$filename = dirname($filename) . "/" . basename($filename);
        if (strpos($filename, $this->root) === 0)
        {
            $filename = str_replace($this->root."/", "", $filename);
        }
        else
        {
            throw new mtoSoapException("Filename is not under the storage root: " . $filename);
        }
        return $filename;
    }

    private function createEvent($filename, $action)
    {
        $hash = $action == self :: ACTION_DELETE ? "" : md5_file($this->root . "/" . $filename);
        mtoDb :: execute("insert into `".$this->config['queue_table']."` (filename, action, `checksum`) values ('".$filename."', '".intval($action)."', '".$hash."')");
    }

    function log($message, $file = "dfs")
    {
        if ($this->config['logging'])
        {
            if (isset($_SERVER['REQUEST_URI']))
            {
                $message .= "\t" . $_SERVER['REQUEST_URI'];
            }
            if (isset($_SERVER['HTTP_REFERER']))
            {
                $message .= "\t" . $_SERVER['HTTP_REFERER'];
            }
            mtoProfiler :: instance()->logError($message, $file);
        }
    }


    /**
     * local :: master
     */
    function init()
    {
        $sql = array();
        $sql[] = "drop table if exists `".$this->config['client_table']."`";
        $sql[] = "CREATE TABLE `".$this->config['client_table']."` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `ip` int(11) DEFAULT NULL,
              `last_event` int(11) DEFAULT NULL,
              `login` varchar(10) DEFAULT NULL,
              `password` varchar(32) DEFAULT NULL,
              `last_sync` int(11) DEFAULT '0',
              PRIMARY KEY (`id`) 
            ) engine=innodb
        ";
        $sql[] = "drop table if exists `".$this->config['queue_table']."`";
        $sql[] = "CREATE TABLE `".$this->config['queue_table']."` (                                 
             `id` int(11) NOT NULL AUTO_INCREMENT,                    
             `filename` varchar(255) DEFAULT NULL,                    
             `action` int(11) DEFAULT NULL,                           
             `checksum` varchar(32) default NULL,
             PRIMARY KEY (`id`) 
           ) engine=innodb";
        foreach ($sql as $query)
        {
            mtoDb :: execute($query);
        }

        return array('status' => "done", 'message' => "Tables created");

    }

    /**
     * local :: master
     */
    function createWsdl()
    {
        $conf = array(
            'name' => "mtodfs",
            'url' => $this->config['url'],
            'classes' => array(
                'mtoDfsSoapService' => array('path' => dirname(__FILE__) . '/mtoDfsSoapService.class.php', 'url' => $this->config['url'])
            ),
            'out' => "dfs.wsdl"
        );
        mtoSoapService :: createWsdl($conf);
        return array("status" => "done", 'message' => "WSDL Created");
        
    }

    function pushWsdl()
    {
        if (!empty($this->config['allowed_ip_pattern']))
        {
            if (!preg_match($this->config['allowed_ip_pattern'], $_SERVER['REMOTE_ADDR']))
            {
                die("XML");
            }
        }
        header("Content-type: text/xml");
        echo mtoSoapService :: getWsdl("dfs.wsdl");
        die();
    }

    function handleSoapRequest()
    {
        $server = new SoapServer(mtoConf :: instance()->getFile("soap", "wsdl_path") . '/dfs.wsdl');
        $server->setClass("mtoDfsSoapService");
        $server->handle();
    }
        



}