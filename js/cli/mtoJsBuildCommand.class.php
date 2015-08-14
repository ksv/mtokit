<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoJsBuildCommand extends mtoCliBaseCommand
{
    private $path;
    //private $root;
    private $paths;
    private $conf;
    
    
    function execute($args = array())
    {
        $this->conf = mtoConf :: instance();
        $this->path = $this->conf->getFile("js.scripts_path") . "/conf";
        //$this->root = mtoConf :: instance()->get("core", "root");
        $this->build_paths();
        

        $exclude = array();
        foreach ($this->paths['paths'] as $key => $path)
        {
            if (strpos($path, "http") !== 0)
            {
                $exclude[] = $key;
            }
        }
        $this->create_settings($exclude);
        
        foreach ($this->paths['paths'] as $key => $path)
        {
            if (strpos($key, "controller.") === 0)
            {
                if (!empty($args['only']) && $key != "controller." . $args['only'])
                {
                    continue;
                }
                $this->build_pack(array("requirejs", "cfg", $key), str_replace("controller.", "code.", $key));
            }
        }
        if (empty($args['only']))
        {
            $this->build_pack(array_keys($this->paths['paths']), "all");
        }
        
        
        
        
        
        
        
//        $build['include'] = array("requirejs", "cfg", "controller.admin_orders"/*, "module.order_list", "module.order_card"*/);
//        $build['out'] = $root . "/scripts/build/adm.orders.js";
//        file_put_contents($root . "/scripts/build/build.tmp.js", "(" . json_encode($build, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES) . ")");
//        $command = "/usr/local/bin/node " . $root . "/scripts/build/r.js -o " . $root . "/scripts/build/build.tmp.js 2>&1";
//        exec($command, $result);
//        $this->out($result);
        
        
//        unlink($root . "/scripts/build/build.tmp.js");
//        unlink($root . "/scripts/build/settings.tmp.js");
        
    }
    
    private function build_pack($include, $out)
    {
                $build = array(
                    'baseUrl' => ".",
                    'paths' => $this->paths['paths'],
                    'shim' => $this->paths['shim'],
                    'include' => $include,
                    'findNestedDependencies' => true,
                    'out' => $this->conf->getFile("js.assets_path") . "/" . $out . ".js"
                );
                $build['paths']['cfg'] = $this->conf->getFile("js.assets_path") . "/settings.tmp";
                file_put_contents($this->conf->getFile("js.assets_path") . "/build.tmp.js", "(" . json_encode($build, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES) . ")");
                $command = "/usr/local/bin/node " . __DIR__ . "/../build/r.js -o " . $this->conf->getFile("js.assets_path") . "/build.tmp.js 2>&1";
                exec($command, $result);
                $this->out($result);
    }
    
    private function build_paths()
    {
        $paths_json = file_get_contents($this->path . "/settings.paths.js");
        $paths_json = str_replace("cfg['uri_cdn'] + \"/js/", '"'.$this->conf->get("core.__mtopath__").'/mtokit/js/', $paths_json);
        $paths_json = str_replace("\" + cfg['lang']", "ru\"", $paths_json);
        $paths_json = str_replace(array('(', ')'), "", $paths_json);
        $paths_json = str_replace("\"/".$this->conf->get('js.scripts_path'), "\"" . $this->conf->getFile("js.scripts_path"), $paths_json);
        $this->paths = json_decode($paths_json, true);
        if (json_last_error() != JSON_ERROR_NONE)
        {
            throw new mtoException("JSON DECODE ERROR: " . json_last_error());
        }
    }
    
    private function create_settings($exclude = array())
    {
        $paths = file_get_contents($this->path . "/settings.paths.js");
        foreach ($exclude as $file)
        {
            $paths = preg_replace("#\"".$file."\".+/.+\n#", "", $paths);
        }
        $all = file_get_contents($this->path . "/settings.all.js");
        $all = preg_replace("#<!--\# .+ -->#", $paths, $all);
        $settings = file_get_contents($this->path . "/settings." . mtoConf :: instance()->get("core.suffix") . ".js");
        $settings = preg_replace("#<!--\# .+ -->#", $all, $settings);
        file_put_contents($this->conf->getFile("js.assets_path") . "/settings.tmp.js", $settings);
    }

    function infoName()
    {
        return "js:js_build";
    }
}
