<?php
require_once(__DIR__ . "/../core/traits/mtoSingletone.trait.php");
require_once(__DIR__ . "/../core/traits/mtoFacade.trait.php");

class mtoConf
{
    private $config = array();
    private $suffix = "";
    private $files = array();
    private $env = array();
    
    use mtoSingletone;
    use mtoFacade;

    function __construct($args = array())
    {
        $this->initConfig();
        if (isset($args['filename']))
        {
            $this->loadConfig($args['filename']);
        }
    }

    function loadConfig($filename = null)
    {
        if (isset($this->files[$filename]))
        {
            return $this;
        }
        $root = $this->get('core.root');
        if (file_exists($filename))
        {
            $config = parse_ini_file($filename, true);
        }
        elseif (!empty($root) && file_exists($this->get('core.root') . "/" . $this->get('core.confdir') . "/" . $filename))
        {
            $config = parse_ini_file($this->get('core.root') . "/" . $this->get('core.confdir') . "/" . $filename, true);
        }
        elseif (strpos($filename, "mtokit") === 0)
        {
            $config = parse_ini_file($this->get('core.__mtopath__') . "/" . $filename, true);
        }
        elseif (!empty($root))
        {
            throw new mtoException("Config not found: " . $filename);
        }
        else
        {
            return;
        }
        $this->files[$filename] = true;
        foreach ($config as $section => $items)
        {
            if (!isset($this->config[$section]))
            {
                $this->config[$section] = array();
            }
            foreach ($items as $key => $value)
            {
                if ($section == "core" && $key == "suffix")
                {
                    $this->suffix = $value;
                }
                if (isset($this->config[$section][$key]) && is_array($this->config[$section][$key]) && is_array($value))
                {
                    $this->config[$section][$key] = array_merge($this->config[$section][$key], $value);
                }
                else
                {
                    $this->config[$section][$key] = $value;
                }
            }
        }
        return $this;
    }

    function getSection($section)
    {
        if (isset($this->config[$section . "_" . $this->suffix]))
        {
            return $this->config[$section . "_" . $this->suffix];
        }
        if (isset($this->config[$section]))
        {
            return $this->config[$section];
        }
        return array();
    }

    function setSection($section, array $data)
    {
        $this->config[$section] = $data;
    }

    function get($section, $param = null, $url = false)
    {
        if (is_null($param) && strpos($section, ".") !== false)
        {
            list($section, $param) = explode(".", $section);
        }
        if (is_null($param) && strpos($section, "|") !== false)
        {
            list($section, $param) = explode("|", $section);
        }
        if (isset($this->config[$section . "_" . $this->suffix][$param]))
        {
            $val = $this->config[$section . "_" . $this->suffix][$param];
        }
        elseif (isset($this->config[$section][$param]))
        {
            $val = $this->config[$section][$param];
        }
        else
        {
            $val = null;
        }
        if ($url)
        {
            if (!empty($this->config['core']['use_https']))
            {
                $val = preg_replace("#^http://#", "https://", $val);
            }
        }
        return $val;
    }
    
    function val($key)
    {
        if (strpos($key, "|") !== false)
        {
            list($section, $param) = explode("|", $key);
        }
        else if (strpos($key, ".") !== false)
        {
            list($section, $param) = explode(".", $key);
        }
        else
        {
            return null;
        }
        return $this->get($section, $param);
    }

    function set($section, $param, $value = null)
    {
        if (strpos($section, ".") !== false)
        {
            $value = $param;
            list($section, $param) = explode(".", $section);
        }
        if (strpos($section, "|") !== false)
        {
            $value = $param;
            list($section, $param) = explode("|", $section);
        }
        if (!isset($this->config[$section]))
        {
            $this->config[$section] = array();
        }
        $this->config[$section][$param] = $value;
    }



    function getFile($section, $param = null)
    {
        return $this->get("core.root") . "/" . $this->get($section, $param);
    }

    function getFilename($filename)
    {
        return $this->get("core.root") . "/" . $filename;
    }

    function handleHttps()
    {
        if (!empty($_SERVER['HTTPS']))
        {
            $this->set("core.use_https", 1);
            $this->set("core.protocol", "https://");
        }
        else
        {
            $this->set("core.protocol", "http://");
        }
    }

    function env($name = null, $value = null, $append = false)
    {
        if (is_null($name))
        {
            return $this->env;
        }
        if (!is_null($value))
        {
            if ($append)
            {
                if (!isset($this->env[$name]))
                {
                    $this->env[$name] = array();
                }
                if ($append === true)
                {
                    $this->env[$name][] = $value;
                }
                else
                {
                    $this->env[$name][$append] = $value;
                }
            }
            else
            {
                $this->env[$name] = $value;
            }
        }
        return isset($this->env[$name]) ? $this->env[$name] : null;
    }

    function hasEnv($name)
    {
        return isset($this->env[$name]) && !empty($this->env[$name]);
    }

    static function getEnv($name = null, $value = null, $append = null)
    {
        return self :: instance()->env($name, $value, $append);
    }

    private function initConfig()
    {
        $this->config = [
            'core' => [
                'timezone' => 'Europe/Moscow'
            ],
            'cache' => [
                'redis' => 'redis://localhost:6379',
                'memcache' => 'memcache://localhost:11211'
            ],
            'cache_args' => [
                'path' => 'var/cache',
                'url' => '/cache',
                'gen' => [
                    'common' => 'mtokit/cache/generator/mtoCommonFilenameGenerator'
                ]
            ],
            'view' => [
                'ext_twig' => 'twig',
                'ext_tpl' => 'mlte'
            ],
            'webapp' => [
                'route' => [
                    '/|controller=frontpage;action=default',
                    '/[controller]/[action]/[id]',
                    '/[controller]/[action]',
                    '/[controller]|action=default'
                ]
            ],
            'cli' => [
                'commands' => [
                    'list' => 'mtokit/cli/cli/mtoCliListCommandsCommand',
                    'help' => 'mtokit/cli/cli/mtoCliCommandHelpCommand',
                    'cron' => 'mtokit/dfs/cli/mtoCronCommand',
                    'console' => 'mtokit/cli/cli/mtoCliConsoleCommand',

                    'cache:tool' => 'mtokit/cache/cli/mtoCacheToolCommand',

                    'cli:cli_backup_svn' => 'mtokit/cli/cli/mtoCliBackupSvnCommand',

                    'config:collect_conf' => 'mtokit/config/cli/mtoCollectConfCommand',
                    'config:compile_conf' => 'mtokit/config/cli/mtoCompileConfCommand',

                    'system:backup_database' => 'mtokit/db/cli/mtoDbBackupCommand',
                    'system:sphinx' => 'mtokit/db/cli/mtoDbSphinxCommand',

                    'dfs:cdn_clean' => 'mtokit/dfs/cli/mtoCdnCleanCommand',
                    'dfs:cdn_heartbeat' => 'mtokit/dfs/cli/mtoCdnHeartbeatCommand',
                    'dfs:cdn_mass' => 'mtokit/dfs/cli/mtoCdnMassCommand',
                    'dfs:cdn_ping' => 'mtokit/dfs/cli/mtoCdnPingCommand',
                    'dfs:cdn_quarantine' => 'mtokit/dfs/cli/mtoCdnQuarantineCommand',
                    'dfs:cdn_sync_common' => 'mtokit/dfs/cli/mtoCdnSyncCommonCommand',
                    'dfs:cdn_sync_data' => 'mtokit/dfs/cli/mtoCdnSyncDataCommand',
                    'dfs:cdn_sync_section' => 'mtokit/dfs/cli/mtoCdnSyncSectionCommand',
                    'dfs:cdn_sync_shared' => 'mtokit/dfs/cli/mtoCdnSyncSharedCommand',
                    'dfs:dfs_clean' => 'mtokit/dfs/cli/mtoDfsCleanCommand',
                    'dfs:dfs_register' => 'mtokit/dfs/cli/mtoDfsRegisterCommand',
                    'dfs:dfs_update' => 'mtokit/dfs/cli/mtoDfsUpdateCommand',

                    'fs:fs_smart_cleanup' => 'mtokit/fs/cli/mtoFsSmartCleanupCommand',

                    'geo:geo_update' => 'mtokit/geo/cli/mtoGeoUpdateCommand',

                    'js:css_build' => 'mtokit/js/cli/mtoCssBuildCommand',
                    'js:js_build' => 'mtokit/js/cli/mtoJsBuildCommand',
                    'js:make_release' => 'mtokit/js/cli/mtoMakeReleaseCommand',

                    'mailapi:mailapi_sync_lists' => 'mtokit/mailapi/cli/mtoMailapiSyncListsCommand',

                    'proc:proc_deploy_service' => 'mtokit/proc/cli/mtoProcDeployServiceCommand',
                    'proc:sync_time' => 'mtokit/proc/cli/mtoSyncTimeCommand',

                    'profiler:profiler_log_tool' => 'mtokit/profiler/cli/mtoProfilerLogToolCommand',

                    'queue:queue_process' => 'mtokit/queue/cli/mtoQueueProcessCommand',
                    'queue:check' => 'mtokit/queue/cli/mtoQueueCheckCommand',

                    'wsdl:generate_wsdl' => 'mtokit/wsdl/cli/mtoGenerateWsdlCommand'
                ]
            ]
        ];
    }





}

