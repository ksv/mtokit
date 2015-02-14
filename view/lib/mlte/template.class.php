<?php

require_once(dirname(__FILE__) . "/tpltags/TemplateTagAbstract.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagIf.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagInsertJavascript.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagJavascript.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagTemplate.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagVar.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagImage.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagDbtable.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagLogged.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagUserInfo.class.php");
require_once(dirname(__FILE__) . "/tpltags/TemplateTagBlock.class.php");

class Template
{

    protected $classname = "Template";
    public $_tpldata = array();
    private $files = array();
    private $root = "";
    private $compiled_code = array();
    private $uncompiled_code = array();
    private $handles = array();
    public $callback;
    private $_tables = array();
    private $_default_table_template;
    private $_table_skip_keys = array();
    private $num_templates = 0;
    private $num_handles = 0;
    public $debug = false;
    private $_cond_replacement = array(
        'eq' => "==",
        'gt' => ">",
        'lt' => "<",
        'ne' => "!="
    );
    private $tag_handlers = array();
    private $included_js = array();
    private $raw = array();
    private $compile_callback = array();

    function __construct($root = ".", $skip_debug = false)
    {
        $this->set_rootdir($root);
        $this->_default_table_template = "PHRhYmxlIHdpZHRoPTkwJSBhbGlnbj1jZW50ZXIgY2VsbHNwYWNpbmc9MSBjZWxscGFkZGluZz0zIGNsYXNzPWFkbWluX2JnPgoJPHRyPgoJPCEtLSBCRUdJTiBoZWFkIC0tPgoJPHRkIGNsYXNzPWFkbWluX2hlYWRlciBhbGlnbj1jZW50ZXI+e2hlYWQuSEVBRH08L3RkPgoJPCEtLSBFTkQgaGVhZCAtLT4gCgk8L3RyPgoJPCEtLSBCRUdJTiByb3cgLS0+Cgk8dHI+CgkJPCEtLSBCRUdJTiBjZWxsIC0tPgoJCTx0ZCBjbGFzcz1hZG1pbl90ZXh0IHN0eWxlPSJiYWNrZ3JvdW5kLWNvbG9yOntyb3cuQkdDT0xPUn0iPntyb3cuY2VsbC5WQUxVRX08L3RkPgoJCTwhLS0gRU5EIGNlbGwgLS0+Cgk8L3RyPgoJPCEtLSBFTkQgcm93IC0tPgo8L3RhYmxlPg==";
        $this->_table_skip_keys = array("handle", "template", "order", "url");
        if (mtoConf :: instance()->get("core", "debug") && !$skip_debug)
        {
            $media = mtoToolkit :: instance()->getRequest()->getInteger("media");
            if ($media != Article :: MEDIA_RSS)
            {
                $this->debug = true;
            }
        }
        $this->raw = array();
    }

    public function destroy()
    {
        $this->_tpldata = array();
    }

    public function register_callback($object, $method)
    {
        $this->compile_callback = array('instance' => $object, 'method' => $method);
    }

    public function set_rootdir($dir)
    {
        if (!is_dir($dir))
        {
            return false;
        }

        $this->root = $dir;
        return true;
    }

    public function set_filenames($filename_array)
    {
        if (!is_array($filename_array))
        {
            return false;
        }

        reset($filename_array);
        while (list($handle, $filename) = each($filename_array))
        {
            $this->files[$handle] = $this->make_filename($filename);
        }

        return true;
    }

    public function pparse($handle, $quiet = false)
    {
        $this->num_templates++;
        if (!$this->loadfile($handle))
        {
            die("Template->pparse(): Couldn't load template file for handle $handle");
            //throw new pdException("Template->pparse(): Couldn't load template file for handle $handle");
        }
        $c = $this->compile($handle);
        $this->compiled_code[$handle] = eval($c);

        //FIXME: in should be removed to caching compiled code
        $this->compiled_code[$handle] = $this->postexec_code($this->compiled_code[$handle]);
        if ($quiet == true)
        {
            return $this->compiled_code[$handle];
        }
        else
        {
            echo $this->compiled_code[$handle];
        }
        return true;
    }

    public function assign_var_from_handle($varname, $handle)
    {
        $this->num_handles++;
        if (!$this->loadfile($handle))
        {
            die("Template->assign_var_from_handle(): Couldn't load template file for handle $handle");
        }
        //file_put_contents('var/eeeeee.block',$this->compile($handle),FILE_APPEND);
        $code = eval($this->compile($handle));
        if (!$code)
        {
            //var_dump($this->compile($handle));
        }
        $this->assign_var($varname, $code);
        return true;
    }

    public function assign_block_vars($blockname, $vararray)
    {
        $result = "";
        foreach ($vararray as $k => $v)
        {
            $vararray[strtoupper($k)] = $v;
        }
        if (strstr($blockname, '.'))
        {

            $ref = $this->generate_block_reference($blockname);
            $ref .= '[] = $vararray;';
            eval($ref);
        }
        else
        {
            $this->_tpldata[$blockname . '.'][] = $vararray;
        }

        return true;
    }

    public function append_block_stub($block, $subblock, $data)
    {
        if (!isset($this->_tpldata[$block . '.']))
        {
            return;
        }
        foreach ($this->_tpldata[$block . '.'] as $k => $v)
        {
            if (isset($v[$subblock . '.'][0]))
            {
                $item = array_shift($data);
                if (is_array($item))
                {
                    foreach ($item as $key => $value)
                    {
                        $this->_tpldata[$block . '.'][$k][$subblock . '.'][0][$key] = $value;
                    }
                }
            }
        }
    }

    public function replace_block_vars($blockname, $index, $vararray)
    {
        foreach ($vararray as $k => $v)
        {
            $vararray[strtoupper($k)] = $v;
        }
        if (strstr($blockname, '.'))
        {
            $ref = $this->generate_block_reference($blockname);
            $ref .= '[$index] = $vararray;';
            eval($ref);
        }
        else
        {
            $this->_tpldata[$blockname . '.'][$index] = $vararray;
        }

        return true;
    }

    public function replace_last_block_vars($blockname, $vararray)
    {
        foreach ($vararray as $k => $v)
        {
            $vararray[strtoupper($k)] = $v;
        }
        if (strstr($blockname, '.'))
        {
            $ref = $this->generate_block_reference($blockname);
//                $ref .= '[$index] = $vararray;';
            $code = 'array_pop(' . $ref . ');';
            $code .= 'array_push(' . $ref . ', $vararray);';
            eval($code);
        }
        else
        {
            array_pop($this->_tpldata[$blockname . '.']);
            array_push($this->_tpldata[$blockname . '.'], $vararray);
        }

        return true;
    }

    public function get_last_block_vars($blockname)
    {
        if (strstr($blockname, '.'))
        {
            $ref = $this->generate_block_reference($blockname);
            $code = '$result = array_pop(' . $ref . ');';
            eval($code);
            return $result;
        }
        else
        {
            return array_pop($this->_tpldata[$blockname . '.']);
            array_push($this->_tpldata[$blockname . '.'], $vararray);
        }
    }

    function generate_block_reference($blockname)
    {
        $lastiteration = 0;
        $blocks = explode('.', $blockname);
        $blockcount = count($blocks) - 1;
        $str = '$this->_tpldata';
        for ($i = 0; $i < $blockcount; $i++)
        {
            $str .= '[\'' . $blocks[$i] . '.\']';
            eval('$lastiteration = count(' . $str . ') - 1;');
            $str .= '[' . $lastiteration . ']';
        }
        $str .= '[\'' . $blocks[$blockcount] . '.\']';
        return $str;
    }

//[] = $vararray;

    public function assign_raw($data, $key="")
    {
        if (!empty($key))
        {
            $this->data['raw'][$key] = $data;
        }
        else
        {
            $this->data['raw'] = $data;
        }
    }

    public function assign_vars($vararray, $toupper = false)
    {
        reset($vararray);
        while (list($key, $val) = each($vararray))
        {
            if ($toupper)
                $key = strtoupper($key);
            $this->_tpldata['.'][0][$key] = $val;
        }
        return true;
    }

    function get_assigned_vars()
    {
        if (isset($this->_tpldata['.'][0]))
        {
            return $this->_tpldata['.'][0];
        }
        else
        {
            return array();
        }
    }

    public function assign_var($varname, $varval)
    {
        $this->_tpldata['.'][0][$varname] = $varval;

        return true;
    }

    public function add_included_js($src)
    {
        if (file_exists(mtoConf :: instance()->get("core", "root") . $src))
        {
            $this->included_js[] = $src;
        }
        else
        {
            die("Javascript ".mtoConf :: instance()->get("core", "root") . $src." does not exist");
        }
    }

    public function get_included_js()
    {
        return $this->included_js;
    }

    private function make_filename($filename)
    {
        // Check if it's an absolute or relative path.
        if (substr($filename, 0, 1) != '/')
        {
            $filename = $this->root . '/' . $filename;
        }

        if (!file_exists($filename))
        {
            die("Template->make_filename(): Error - file $filename does not exist");
            //throw new pdException("Template->make_filename(): Error - file $filename does not exist");
        }

        return $filename;
    }

    private function loadfile($handle)
    {
        // If the file for this handle is already loaded and compiled, do nothing.
        if (isset($this->uncompiled_code[$handle]) && !empty($this->uncompiled_code[$handle]))
        {
            return true;
        }

        // If we don't have a file assigned to this handle, die.
        if (!isset($this->files[$handle]))
        {
            $msg = array();
            $msg[] = "[" . $handle . "]";
            $msg[] = $_SERVER['REQUEST_URI'];
            $msg[] = isset($_SERVER['HTTP_REFERER']) ? "[" . $_SERVER['HTTP_REFERER'] . "]" : "[]";
            $msg[] = print_r($_REQUEST, true);
            $msg[] = _D(debug_backtrace(), true, true, true);
            mtoProfiler :: instance()->logDebug(implode("\t", $msg), "debug/template_error");
            die("Template->loadfile({$this->files[$handle]}): No file specified for handle $handle");
            //throw new pdException("Template->loadfile(): No file specified for handle $handle");
        }

        $filename = $this->files[$handle];

        $str = file_get_contents($filename);
        if (empty($str))
        {
            //print_r(debug_backtrace());
            die("Template->loadfile({$this->files[$handle]}): File $filename for handle $handle is empty");
        }
        $tpl = $this->_truncate_block($str);
        preg_match_all("#{([A-Z_]+)}#siU", $tpl, $matches);
        if (is_array($matches))
        {
            foreach ($matches[1] as $match)
            {
                $this->handles[] = $match;
            }
        }

        $this->uncompiled_code[$handle] = $str;

        return true;
    }

    private function prepare_code($code)
    {
        //parsing constants
        //format: {const:CONSTANT_NAME}
        if (preg_match_all("#{const\:(.+)}#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (isset($match[1]))
                {
                    if (defined($match[1]))
                    {
                        $code = str_replace("{const:" . $match[1] . "}", constant($match[1]), $code);
                    }
                    else
                    {
                        $code = str_replace("{const:" . $match[1] . "}", "", $code);
                    }
                }
                else
                {
                    $code = str_replace("{const:" . $match[1] . "}", "", $code);
                }
            }
        }
        //parsing variables
        //format: {var:VARIABLE_NAME}
        if (preg_match_all("#{var\:(.+)}#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (isset($match[1]))
                {
                    $varname = $match[1];
                    global $$varname;
                    if (isset($$varname))
                    {
                        $code = str_replace("{var:" . $match[1] . "}", $$varname, $code);
                    }
                    else
                    {
                        $code = str_replace("{var:" . $match[1] . "}", "", $code);
                    }
                }
                else
                {
                    $code = str_replace("{var:" . $match[1] . "}", "", $code);
                }
            }
        }
        //parsing template variables
        //format: {tplvar:VARIABLE_NAME}
        if (preg_match_all("#{tplvar\:(.+)}#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (isset($match[1]))
                {
                    $varname = $match[1];
                    if (isset($this->_tpldata['.'][0][$varname]))
                    {
                        $code = str_replace("{tplvar:" . $match[1] . "}", $this->_tpldata['.'][0][$varname], $code);
                    }
                    else
                    {
                        $code = str_replace("{tplvar:" . $match[1] . "}", "", $code);
                    }
                }
                else
                {
                    $code = str_replace("{tplvar:" . $match[1] . "}", "", $code);
                }
            }
        }
        //parsing environment variables
        //format: {env:[gpcse]:NAME}
        if (preg_match_all("#{env\:([gpcrseS]+)\:(.+)}#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (isset($match[1]) && isset($match[2]))
                {
                    if ((isset($_REQUEST[$match[2]]) && strpos($match[1], "g") !== false) || (isset($_REQUEST[$match[2]]) && strpos($match[1], "p") !== false) || (isset($_COOKIE[$match[2]]) && strpos($match[1], "c") !== false) || (isset($_SERVER[$match[2]]) && strpos($match[1], "s") !== false) || (isset($_ENV[$match[2]]) && strpos($match[1], "e") !== false) || (isset($_SESSION[$match[2]]) && strpos($match[1], "S") !== false) || (isset($_REQUEST[$match[2]]) && strpos($match[1], "r") !== false))
                    {
                        $var = "";
                        if (isset($_REQUEST[$match[2]]))
                            $var = $_REQUEST[$match[2]];
                        if (isset($_REQUEST[$match[2]]))
                            $var = $_REQUEST[$match[2]];
                        if (isset($_REQUEST[$match[2]]))
                            $var = $_REQUEST[$match[2]];
                        if (isset($_COOKIE[$match[2]]))
                            $var = $_COOKIE[$match[2]];
                        if (isset($_SERVER[$match[2]]))
                            $var = $_SERVER[$match[2]];
                        if (isset($_ENV[$match[2]]))
                            $var = $_ENV[$match[2]];
                        if (isset($_SESSION[$match[2]]))
                            $var = $_SESSION[$match[2]];
                        $code = str_replace("{env:" . $match[1] . ":" . $match[2] . "}", $var, $code);
                    }
                    else
                    {
                        $code = str_replace("{env:" . $match[1] . ":" . $match[2] . "}", "0", $code);
                    }
                }
                else
                {
                    $code = str_replace("{env:" . $match[1] . ":" . $match[2] . "}", "", $code);
                }
            }
        }

        //parsing blocks
        if (preg_match_all("#{block:(\w+) (.+)}#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $block_name = $match[1];
                $block_info = $match[2];
                $block_params = array();
                $pairs = explode(" ", $block_info);
                if (is_array($pairs))
                {
                    foreach ($pairs as $pair)
                    {
                        $elems = explode("=\"", $pair, 2);
                        if (is_array($elems) && count($elems) == 2)
                        {
                            $block_params[$elems[0]] = str_replace("\"", "", $elems[1]);
                        }
                    }
                }
                if (!empty($block_name))
                {
                    if (isset($block_params['show']) && $block_params['show'] == "yes")
                    {
                        $block_name = "block_" . $block_name;
                        $block_html = Block::$block_name($block_params);
                        $code = str_replace("{block:" . $match[1] . " " . $match[2] . "}", $block_html, $code);
                    }
                    else
                    {
                        $code = str_replace("{block:" . $match[1] . " " . $match[2] . "}", "", $code);
                    }
                }
            }
        }
        //direct phpcode executions
        //format: {PHPCODE}code to execute{/PHPCODE}
        //NOTE: code to execute MUST be valid php code
        //NOTE: code MUST prepare output and RETURN it. Direct output instructions are depricated
        //NOTE: code will be executed before template compilation.
        if (preg_match_all("#{PHPCODE}(.+){/PHPCODE}#simU", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (isset($match[1]) && !empty($match[1]))
                {
                    ob_start();
                    $res = eval($match[1]);
                    $buf = ob_get_clean();
                    $str = $res . (!empty($buf) ? "\n<br /><b>EVAL RETURN:</b>\n\n<br /><br />" . $buf . "\n\n<br /><br />" : "");
                    $code = str_replace("{PHPCODE}" . $match[1] . "{/PHPCODE}", $str, $code);
                }
                else
                {
                    $code = str_replace("{PHPCODE}" . $match[1] . "{/PHPCODE}", "", $code);
                }
            }
        }
        //conditions
        //format: {IF condition=""}template{ELSE}template{/IF}
        //NOTE: olny root level assigments may be used for conditions. it will available witout {tplvar:}
        if (preg_match_all("#{IF (.+)}(.+){/IF}#simU", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (strstr($match[2], "{ELSE}") !== false)
                {
                    list($if_section, $else_section) = explode("{ELSE}", $match[2]);
                }
                else
                {
                    $if_section = $match[2];
                    $else_section = "";
                }
                if (!preg_match("#condition=\"(.+)\"#siU", $match[1], $cmatches))
                    die("Template: conditional block broken");
                $condition = $cmatches[1];
                $condition = str_replace(array_keys($this->_cond_replacement), array_values($this->_cond_replacement), $condition);
                $condition = "return " . $condition . ";";

                if (preg_match("#\\$([\w_]+)[\s\"]#simU", $condition, $cmatches))
                {
                    $condition = str_replace("$" . $cmatches[1], $this->_tpldata['.'][0][$cmatches[1]], $condition);
                }
                if (eval($condition))
                {
                    $code = str_replace("{IF " . $match[1] . "}" . $match[2] . "{/IF}", $if_section, $code);
                }
                else
                {
                    $code = str_replace("{IF " . $match[1] . "}" . $match[2] . "{/IF}", $else_section, $code);
                }
            }
        }

        return $code;
    }

    function postexec_code($code)
    {
        //process <image:resize> directives
        if (preg_match_all("#\<image:resize (.+)/\>#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $image_info = $match[1];
                $image_params = array();
                $pairs = explode(" ", $image_info);
                if (is_array($pairs))
                {
                    foreach ($pairs as $pair)
                    {
                        $elems = explode("=", $pair);
                        if (is_array($elems) && count($elems) == 2)
                        {
                            $image_params[$elems[0]] = str_replace("\"", "", $elems[1]);
                        }
                    }
                }


                if (!empty($image_params['src']) && !empty($image_params['width']) && !empty($image_params['height']))
                {

                    if (empty($image_params['scope']))
                    {
                        throw new mtoException("Cache scope not found");
                    }
                    if ($image_params['scope'] == "direct")
                    {
                        $path = $image_params['prefix'] . $image_params['src'];
                    }
                    else
                    {
                        $cache = mtoToolkit :: instance()->getCache($image_params['scope']);
                        if ($image_params['scope'] == "product")
                        {
                            $image_params['src'] = new Product($image_params['id']);
                        }
                        $cache_args = array(
                            'id' => $image_params['id'],
                            'w' => $image_params['width'],
                            'h' => $image_params['height']
                        );
                        if (isset($image_params['option']))
                        {
                            $cache_args['option_id'] = $image_params['option'];
                        }
                        if (isset($image_params['effect']))
                        {
                            $cache_args['effect'] = $image_params['effect'];
                        }
                        $path = $cache->get($image_params['src'], $cache_args);
                    }


                    if ($path == -1)
                    {
                        $rstr = "<img src='/index.php?mode=thumb&src=" . $image_params['src'] . "&type=image&w=" . $image_params['width'] . "&h=" . $image_params['height'] . "&option=" . (isset($image_params['option']) ? $image_params['option'] : "") . "&effect=" . (isset($image_params['effect']) ? constant($image_params['effect']) : "") . "&product_id=" . (isset($image_params['product_id']) ? $image_params['product_id'] : "") . "&side=" . (isset($image_params['side']) ? $image_params['side'] : "") . "&no_cache=" . (isset($image_params['no_cache']) ? $image_params['no_cache'] : "0") . "&nc=" . (isset($image_params['no_clip']) && $image_params['no_clip'] ? "1" : "0") . "' alt='" . (isset($image_params['alt']) ? $image_params['alt'] : "") . "' width='" . $image_params['width'] . "' height='" . $image_params['height'] . "' class='" . (isset($image_params['class']) ? $image_params['class'] : "") . "' border='" . (isset($image_params['border']) ? $image_params['border'] : "0") . "' />";
                    }
                    else
                    {
                        $rstr = "<img src='" . $path . "' title='CDN: $path' width='" . $image_params['width'] . "' height='" . $image_params['height'] . "' class='" . (isset($image_params['class']) ? $image_params['class'] : "") . "' border='" . (isset($image_params['border']) ? $image_params['border'] : "0") . "' />";
                    }

                    if (!empty($image_params['src']) && !empty($image_params['save_images']) && $image_params['save_images'])
                    {
                        $rstr = "<img src='" . $image_params['src'] . "' width='" . $image_params['width'] . "' height='" . $image_params['height'] . "' class='" . (isset($image_params['class']) ? $image_params['class'] : "") . "' border='" . (isset($image_params['border']) ? $image_params['border'] : "0") . "' />";
                    }

                    $code = str_replace($match[0], $rstr, $code);
                }
                else
                {
                    $code = str_replace($match[0], "", $code);
                }
            }
        }

        //process <block> directives
        if (preg_match_all("#\<block (.+)/\>#U", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $block_info = $match[1];
                $block_params = array();
                $pairs = explode(" ", $block_info);
                if (is_array($pairs))
                {
                    foreach ($pairs as $pair)
                    {
                        $elems = explode("=", $pair);
                        if (is_array($elems) && count($elems) == 2)
                        {
                            $block_params[$elems[0]] = str_replace('"', '', $elems[1]);
                        }
                    }
                }
                $method = "block_" . $block_params['name'];
                if (method_exists("Block", $method))
                {
                    $code = str_replace("<block " . $match[1] . "/>", Block::$method($block_params), $code);
                }
                else
                {
                    $code = str_replace("<block " . $match[1] . "/>", "", $code);
                }
            }
        }
        return $code;
    }

    private function compile($handle)
    {
        static $in_callback;
        if (!isset($this->uncompiled_code[$handle]))
            throw new pdException("handle_not_exist");
        $code = $this->uncompiled_code[$handle];
        $code = $this->prepare_code($code);
        if ($in_callback != 1)
        {
            $in_callback = 1;
            foreach ($this->handles as $key => $hdl)
            {
                if (isset($this->_tpldata['.'][0][$hdl]))
                {
                    unset($this->handles[$key]);
                }
            }
            if (is_array($this->handles) && count($this->handles) > 0 && isset($this->compile_callback['instance']) && isset($this->compile_callback['method']))
            {
                $method = $this->compile_callback['method'];
                $handles_data = $this->compile_callback['instance']->$method($this->handles);
                foreach ($handles_data as $handle => $data)
                {
                    $this->assign_vars(array(
                        $handle => $data
                    ));
                    unset($this->handles[$handle]);
                }
            }
            $in_callback = 0;
        }



        $parsed_block = false;
        if ($this->debug)
        {
            $code = "\n\n\n<!-- ** START: " . (isset($this->files[$handle]) ? $this->files[$handle] : "generic template " . $handle) . " ** -->\n" . $code . "\n<!-- ** END: " . (isset($this->files[$handle]) ? $this->files[$handle] : "generic template " . $handle) . " ** -->\n\n\n";
        }

        $code = str_replace('\\', '\\\\', $code);
        $code = str_replace('\'', '\\\'', $code);


        $old = error_reporting();
        error_reporting(0);
        $code = preg_replace("#\{\{([A-Z_]+)\}\}#Ue", '$this->_tpldata["."][0]["$1"]', $code);
        error_reporting($old);


        //tags without params
        if (preg_match_all("#\<tpl:([a-z]+)(\>.*)(\</tpl:\\1\>)#simU", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $tagHandler = $this->get_tag_handler($match);
                $code = $tagHandler->compile($code, $this);
            }
        }

        //self closed tags with params
        if (preg_match_all("#\<tpl:([a-z_]+) ([^\>]*)(\/\>)#simU", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                $tagHandler = $this->get_tag_handler($match);
                $code = $tagHandler->compile($code);
            }
        }

        //tag with params
        //FIXME: it now supports only 2-level nesting
        if (preg_match_all("#\<tpl:([a-z_]+) (.*)(\</tpl:\\1\>)#simU", $code, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $match)
            {
                if (strpos($match[2], "<tpl:") !== false)
                {
                    if (preg_match_all("#\<tpl:([a-z_]+) (.*)(\</tpl:\\1\>|\/\>)#simU", $match[2], $submatches, PREG_SET_ORDER))
                    {
                        foreach ($submatches as $submatch)
                        {
                            $tagHandler = $this->get_tag_handler($submatch);
                            $match[2] = $tagHandler->compile($match[2]);
                        }
                    }
                }
                $tagHandler = $this->get_tag_handler($match);
                $code = $tagHandler->compile($code);
            }
        }


        $code = $this->replace_tokens($code);

        $matches = array();

        $code_lines = explode("\n", $code);

        $block_nesting_level = 0;
        $block_names = array();
        $block_names[0] = ".";

        $line_count = count($code_lines);
        $m = null;
        for ($i = 0; $i < $line_count; $i++)
        {
            $code_lines[$i] = chop($code_lines[$i]);
            if (preg_match('#<!-- BEGIN (.*?) -->#', $code_lines[$i], $m))
            {
                $n[0] = $m[0];
                $n[1] = $m[1];
                if (preg_match('#<!-- END (.*?) -->#', $code_lines[$i], $n))
                {
                    $block_nesting_level++;
                    $block_names[$block_nesting_level] = $m[1];
                    if ($block_nesting_level < 2)
                    {
                        $code_lines[$i] = '$_' . $n[1] . '_count = ( isset($this->_tpldata[\'' . $n[1] . '.\']) ) ?  sizeof($this->_tpldata[\'' . $n[1] . '.\']) : 0;';
                        $code_lines[$i] .= "\n" . 'for ($_' . $n[1] . '_i = 0; $_' . $n[1] . '_i < $_' . $n[1] . '_count; $_' . $n[1] . '_i++)';
                        $code_lines[$i] .= "\n" . '{';
                    }
                    else
                    {
                        $namespace = implode('.', $block_names);
                        $namespace = substr($namespace, 2);
                        $varref = $this->generate_block_token($namespace, false);
                        $code_lines[$i] = '$_' . $n[1] . '_count = ( isset(' . $varref . ') ) ? sizeof(' . $varref . ') : 0;';
                        $code_lines[$i] .= "\n" . 'for ($_' . $n[1] . '_i = 0; $_' . $n[1] . '_i < $_' . $n[1] . '_count; $_' . $n[1] . '_i++)';
                        $code_lines[$i] .= "\n" . '{';
                    }
                    unset($block_names[$block_nesting_level]);
                    $block_nesting_level--;
                    $code_lines[$i] .= '} // END ' . $n[1];
                    $m[0] = $n[0];
                    $m[1] = $n[1];
                }
                else
                {
                    $block_nesting_level++;
                    $block_names[$block_nesting_level] = $m[1];
                    if ($block_nesting_level < 2)
                    {
                        $code_lines[$i] = '$_' . $m[1] . '_count = ( isset($this->_tpldata[\'' . $m[1] . '.\']) ) ? sizeof($this->_tpldata[\'' . $m[1] . '.\']) : 0;';
                        $code_lines[$i] .= "\n" . 'for ($_' . $m[1] . '_i = 0; $_' . $m[1] . '_i < $_' . $m[1] . '_count; $_' . $m[1] . '_i++)';
                        $code_lines[$i] .= "\n" . '{';
                    }
                    else
                    {
                        $namespace = implode('.', $block_names);
                        $namespace = substr($namespace, 2);
                        $varref = $this->generate_block_token($namespace, false);
                        $code_lines[$i] = '$_' . $m[1] . '_count = ( isset(' . $varref . ') ) ? sizeof(' . $varref . ') : 0;';
                        $code_lines[$i] .= "\n" . 'for ($_' . $m[1] . '_i = 0; $_' . $m[1] . '_i < $_' . $m[1] . '_count; $_' . $m[1] . '_i++)';
                        $code_lines[$i] .= "\n" . '{';
                    }
                }
            }
            else if (preg_match('#<!-- END (.*?) -->#', $code_lines[$i], $m))
            {
                unset($block_names[$block_nesting_level]);
                $block_nesting_level--;
                $code_lines[$i] = '} // END ' . $m[1];
            }
            elseif (preg_match("#<!-- PARSED -->#", $code_lines[$i]))
            {
                $parsed_block = true;
                $code_lines[$i] = "";
                continue;
            }
            elseif (preg_match("#<!-- /PARSED -->#", $code_lines[$i]))
            {
                $parsed_block = false;
                $code_lines[$i] = "";
                continue;
            }
            elseif ($parsed_block)
            {
                continue;
            }
            else
            {
                $code_lines[$i] = '$retvar .= \'' . $code_lines[$i] . '\' . "\\n";';
            }
        }
        $code = '$retvar="";';
        $code .= implode("\n", $code_lines);
        $code .= 'return $retvar;';
        return $code;
    }

    public function replace_tokens($code, $insert_breaks = true)
    {
        $matches = array();
        preg_match_all('#\{(([a-z0-9\-_]+?\.)+?)([a-z0-9\-_]+?)\}#is', $code, $matches);
        for ($i = 0, $count = count($matches[1]); $i < $count; $i++)
        {
            $namespace = substr($matches[1][$i], 0, strlen($matches[1][$i]) - 1);
            $var = $matches[3][$i];
            $varref = $this->generate_block_token($namespace, true);
            $varref .= '[\'' . $var . '\']';
            if ($insert_breaks)
            {
                $varref = '\' . ( ( isset(' . $varref . ') ) ? ' . $varref . ' : \'\' ) . \'';
            }
            else
            {
                $varref = '( ( isset(' . $varref . ') ) ? ' . $varref . ' : \'\' )';
            }

            $code = str_replace($matches[0][$i], $varref, $code);
        }

        $code = preg_replace('#\{([a-z0-9\-_]*?)\}#is', '\' . ( ( isset($this->_tpldata[\'.\'][0][\'\1\']) ) ? $this->_tpldata[\'.\'][0][\'\1\'] : \'\' ) . \'', $code);
        return $code;
    }

    private function get_tag_handler(array $args)
    {
        if (count($args) != 4)
            throw new pdException("template_compilation_error", array(__LINE__, __FILE__));
        $class_name = "TemplateTag" . mto_camel_case($args[1]);
        //if (strpos($args[2], "<") !== false) throw new CoreException("template_compilation_error");
        if (!class_exists($class_name))
            throw new pdException("class_not_exist");
        if (!isset($this->tag_handlers[$class_name]))
        {
            $this->tag_handlers[$class_name] = new $class_name($args, $this);
        }
        else
        {
            $this->tag_handlers[$class_name]->init($args);
        }
        return $this->tag_handlers[$class_name];
    }

    private function generate_block_token($blockname, $include_last_iterator)
    {
        $blocks = explode(".", $blockname);
        $blockcount = sizeof($blocks) - 1;
        $varref = '$this->_tpldata';
        for ($i = 0; $i < $blockcount; $i++)
        {
            $varref .= '[\'' . $blocks[$i] . '.\'][$_' . $blocks[$i] . '_i]';
        }
        $varref .= '[\'' . $blocks[$blockcount] . '.\']';
        if ($include_last_iterator)
        {
            $varref .= '[$_' . $blocks[$blockcount] . '_i]';
        }

        return $varref;
    }

    private function _truncate_block($target, $blockname="")
    {
        $pattern = "#<!-- BEGIN (" . (($blockname == "" ? ".+" : $blockname)) . ") -->.*<!-- END \\1 -->#siU";
        return preg_replace($pattern, "", $target);
    }

    public function assign_language($language_file, $pattern = "")
    {
        if ($pattern == "")
        {
            require $language_file;
            while (list($key, $value) = each($lang))
            {
                $this->assign_vars(array(
                    "L_" . strtoupper($key) => $value
                ));
            }
        }
        else
        {
            require $language_file;
            while (list($key, $value) = each($lang))
            {
                if (ereg($pattern, $key))
                {
                    $this->assign_vars(array(
                        "L_" . strtoupper($key) => $value
                    ));
                }
            }
        }
    }

    public function assign_form($form_template, $form_handle, $form_data, $form_values=array(), $form_errors=array(), $request_fetch=false)
    {
        $db =  mtoToolkit :: instance()->getDbConnection();
        $this->set_filenames(array("frm" => $form_template));
        if (is_array($form_errors))
        {
            $error_css_classes = parse_ini_file("shared/ini/warnings.ini");
            foreach ($form_errors as $field => $error)
            {
                $this->assign_block_vars("error_line", array(
                    'ERROR_TEXT' => $error['text'],
                    'ERROR_CLASS' => $error_css_classes[$error['type']]
                ));
                $this->assign_vars(array(
                    'ERR_' . mb_strtoupper($field, 'utf-8') => $error['text']
                ));
            }
        }
        $fld = array();
        if ($request_fetch)
        {
            $fld = mtoToolkit :: instance()->getRequest()->getArray("fld");
        }
        foreach ($form_data as $form_key => $form_field)
        {
            if (!isset($form_values[$form_field['field']]))
            {
                $form_values[$form_field['field']] = "";
                if ($request_fetch)
                {
                    $ind = str_replace("fld[", "", str_replace("]", "", $form_field['field']));
                    $form_values[$form_field['field']] = isset($fld[$ind]) ? $fld[$ind] : "";
                }
            }
            if ($form_field['input_type'] == "select")
            {
                $options = "";
                if (isset($form_field['dboptions']) && !empty($form_field['dboptions']))
                {
                    $opts = explode("|", $form_field['dboptions']);
                    if (count($opts) > 2)
                    {
                        if (defined($opts[0]))
                            $opts[0] = constant($opts[0]);
                        $options = mtoToolkit :: instance()->sql_makeoptions($opts[0], $opts[1], $opts[2], $form_values[$form_field['field']], (isset($form_field['show_default']) && $form_field['show_default'] == 1) ? 1 : 0, (isset($form_field['options_where'])) ? $form_field['options_where'] : "", !empty($form_field['default_option']) ? array('value' => 0, 'text' => $form_field['default_option']) : array());
                    }
                    else
                    {
                        $options = "";
                    }
                }
                if (isset($form_field['options']) && !empty($form_field['options']))
                {
                    $eval_code = "\$options=" . $form_field['options'] . "('" . $form_values[$form_field['field']] . "'";
                    if (isset($form_field['options_params']))
                    {
                        $opts = explode("|", $form_field['options_params']);
                        foreach ($opts as $opt)
                        {
                            if ($opt == strtoupper($opt))
                            {
                                if (defined($opt))
                                {
                                    $opt = constant($opt);
                                }
                            }
                            $eval_code.=", " . $opt;
                        }
                    }
                    $eval_code.=");";
                    eval(html_entity_decode($eval_code));
                }
                if (isset($form_field['options_plain']) && !empty($form_field['options_plain']))
                {
                    $options = "";
                    //if (isset($form_field['show_default']) && $form_field['show_default'] == 1)
                    $pairs = explode("|", $form_field['options_plain']);
                    if (is_array($pairs) && count($pairs))
                    {
                        foreach ($pairs as $pair)
                        {
                            $opt = explode("~", $pair);
                            if (isset($opt[0]) && isset($opt[1]))
                            {
                                $sel = ($opt[0] == $form_values[$form_field['field']]) ? "selected" : "";
                                $options .= "<option value='{$opt[0]}' $sel>" . $opt[1] . "\n";
                            }
                        }
                    }
                }
            }
            $field_id = $form_field['field'];
            if (preg_match("#^(.+)\[(\d+)\]$#", $field_id))
            {
                $field_id = preg_replace("#^(.+)\[(\d+)\]$#", "\\1_\\2", $field_id);
            }

            $this->assign_block_vars("form_line", array(
                'NAME' => $form_field['field'],
                'ID' => $field_id,
                'CAPTION' => (isset($form_field['required']) && $form_field['required'] == true) ? $form_field['caption'] . " <span class='red'>*</span>" : $form_field['caption'],
                'CAPTION_BLANK' => html_entity_decode($form_field['caption'], ENT_NOQUOTES, 'utf-8'),
                'OPTIONS' => ($form_field['input_type'] == "select") ? $options : "",
                'SIZE' => (in_array($form_field['input_type'], array("text", "file")) && isset($form_field['size'])) ? $form_field['size'] : "",
                'TITLE' => isset($form_field['title']) ? $form_field['title'] : "",
                'MAXLENGTH' => (in_array($form_field['input_type'], array("text", "file")) && isset($form_field['maxlength'])) ? $form_field['maxlength'] : "",
                'ROWS' => ($form_field['input_type'] == "textarea" && !empty($form_field['rows'])) ? $form_field['rows'] : "",
                'COLS' => ($form_field['input_type'] == "textarea" && !empty($form_field['cols'])) ? $form_field['cols'] : "",
                'CHECKED' => ($form_field['input_type'] == "checkbox" && $form_values[$form_field['field']] == 1) ? "checked" : "",
                'VALUE' => ($form_field['input_type'] == "text" || $form_field['input_type'] == "textarea" || $form_field['input_type'] == "file") ? $form_values[$form_field['field']] : (($form_field['input_type'] == "checkbox") ? "1" : ""),
                'TYPE' => ($form_field['input_type'] == "text" || $form_field['input_type'] == "checkbox" || $form_field['input_type'] == "password") ? $form_field['input_type'] : "",
                'BROWSE_FUNCTION' => (isset($form_field['browse_function'])) ? $form_field['browse_function'] : "",
                'DELIMITER_TEXT' => (isset($form_field['delimiter'])) ? $form_field['delimiter'] : "",
                'ERROR' => isset($form_errors[$form_key]['text']) ? $form_errors[$form_key]['text'] : "",
                'ERR_CLASS' => isset($form_errors[$form_key]['text']) ? "error" : "",
                'FIELD_ID' => isset($form_field['field_id']) ? $form_field['field_id'] : "",
                'ONCHANGE' => isset($form_field['onchange']) ? $form_field['onchange'] : ""
            ));
            if (isset($form_field['latin']) && $form_field['latin'] == "index")
            {
                $this->assign_block_vars("form_line.find_index", array());
            }
            if ($form_field['input_type'] == "text" || $form_field['input_type'] == "checkbox" || $form_field['input_type'] == "password")
            {
                $this->assign_block_vars("form_line.input_section", array());
            }
            if ($form_field['input_type'] == "file")
            {
                $this->assign_block_vars("form_line.file_section", array());
                if (!empty($form_values[$form_field['field']]))
                {
                    $this->assign_block_vars("form_line.file_section.file_uploaded", array());
                }
            }
            if ($form_field['input_type'] == "checkbox")
            {
                $this->assign_block_vars("form_line.hidden_checkbox_section", array());
            }
            if ($form_field['input_type'] == "select")
            {
                $this->assign_block_vars("form_line.select_section", array());
            }
            if ($form_field['input_type'] == "textarea")
            {
                $this->assign_block_vars("form_line.textarea_section", array());
            }
            if (isset($form_field['browse_function']) != "")
            {
                $this->assign_block_vars("form_line.browse_section", array());
            }
            if (isset($form_field['delimiter']))
            {
                $this->assign_block_vars("form_line.delimiter", array());
            }
        }

        $this->assign_var_from_handle($form_handle, "frm");
        $this->unset_block("form_line");
        $this->unset_block("error_line");
    }

    public function unset_block($handle)
    {
        unset($this->_tpldata[$handle . "."]);
    }

    public function unset_compiled_block($handle)
    {
        unset($this->compiled_code[$handle]);
        unset($this->uncompiled_code[$handle]);
    }



    public function create_table($table=array())
    {
        $handler = uniqid(time());
        if (is_array($table))
        {
            $this->_tables[$handler] = $table;
        }
        else
        {
            $this->_tables[$handler] = array();
        }
        if (!isset($this->_tables[$handler]['colors']) || !is_array($this->_tables[$handler]['colors']) || count($this->_tables[$handler]['colors']) == 0)
        {
            $this->_tables[$handler]['colors'] = array("#ffffff");
        }
        return $handler;
    }

    public function set_table_template($tHandler, $template)
    {

        if (isset($this->_tables[$tHandler]))
        {
            $this->_tables[$tHandler]['template'] = $template;
            return true;
        }
        else
        {
            return false;
        }
    }

    public function set_table_params($tHandler, $params)
    {
        if (isset($this->_tables[$tHandler]))
        {
            if (is_array($params))
            {
                foreach ($params as $key => $param)
                {
                    $this->_tables[$tHandler][$key] = $param;
                }
                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }

    public function set_table_data($tHandler, $data)
    {
        if (isset($this->_tables[$tHandler]))
        {
            $this->_tables[$tHandler]['data'] = $data;
            return true;
        }
        else
        {
            return false;
        }
    }

    public function set_table_colors($tHandler, $colors)
    {
        if (isset($this->_tables[$tHandler]))
        {
            $this->_tables[$tHandler]['colors'] = $colors;
            return true;
        }
        else
        {
            return false;
        }
    }

    public function set_table_fields($tHandler, $fields)
    {
        if (isset($this->_tables[$tHandler]))
        {
            $this->_tables[$tHandler]['fields'] = $fields;
            return true;
        }
        else
        {
            return false;
        }
    }

    public function set_table_fields_assoc($tHandler, $fields)
    {
        if (isset($this->_tables[$tHandler]))
        {
            foreach ($fields as $key => $field)
            {
                $field['name'] = $key;
                $this->_tables[$tHandler]['fields'][] = $field;
            }
        }
        else
        {
            return false;
        }
    }

    private function override_last_delimiter(array $data)
    {
        if (isset($this->_tpldata['row.']))
        {
            $rowid = -1;
            for ($i = count($this->_tpldata['row.']) - 1; $i >= 0; $i--)
            {
                if (isset($this->_tpldata['row.'][$i]['delimiter.']))
                {
                    $rowid = $i;
                    break;
                }
            }
            if ($rowid >= 0)
            {
                foreach ($data as $key => $value)
                {
                    $this->_tpldata['row.'][$rowid]['delimiter.'][0][strtoupper($key)] = $value;
                }
            }
        }
    }

    public function compile_table($tHandler)
    {
        global $order_status_options;
        $toolkit = mtoToolkit :: instance();
        $db = $toolkit->getDbConnection();
        if (isset($this->_tables[$tHandler]))
        {
            //fetch template content
            if (isset($this->_tables[$tHandler]['template']) && $this->_tables[$tHandler]['template'] && file_exists($this->root . "/" . $this->_tables[$tHandler]['template']))
            {
                $this->uncompiled_code[$tHandler] = file_get_contents($this->root . "/" . $this->_tables[$tHandler]['template']);
            }
            else
            {
                $this->uncompiled_code[$tHandler] = base64_decode($this->_default_table_template);
            }


            //assign variables
            foreach ($this->_tables[$tHandler] as $key => $value)
            {
                if (!is_array($value) && !in_array($key, $this->_table_skip_keys))
                {
                    $this->assign_vars(array(strtoupper($key) => $value));
                }
            }
            //create table
            if (is_array($this->_tables[$tHandler]['fields']))
            {
                foreach ($this->_tables[$tHandler]['fields'] as $key => $field)
                {
                    if (isset($field['sortable']) && $field['sortable'] == 1)
                    {
                        $this->assign_block_vars("head", array(
                            'HEAD' => "<a href='" . str_replace("%", $field['name'], $this->_tables[$tHandler]['url']) . "'>" . $field['caption'] . "</a>"
                        ));
                    }
                    else
                    {
                        $this->assign_block_vars("head", array(
                            'HEAD' => $field['caption']
                        ));
                    }
                }
                if (is_array($this->_tables[$tHandler]['data']))
                {
                    if (isset($this->_tables[$tHandler]['order']) && $this->_tables[$tHandler]['order'])
                    {
                        $direction = (!empty($this->_tables[$tHandler]['direction'])) ? $this->_tables[$tHandler]['direction'] : SORT_ASC;

                        $this->_tables[$tHandler]['data'] = mtoToolkit::instance()->array_column_sort($this->_tables[$tHandler]['data'], $this->_tables[$tHandler]['order'], $direction);
                    }
                    $rowNum = 0;

                    if (isset($this->_tables[$tHandler]['groupBy']))
                    {
                        $currentGroup = "";
                        $currentGroupCounter = 0;
                    }
                    foreach ($this->_tables[$tHandler]['data'] as $key => $row)
                    {
                        if (isset($row['highlighted']) && $row['highlighted'] == 1 /* && isset($this->_tables[$tHandler]['highlight_color']) */)
                        {
                            //echo 1;
                            //print_r($this->_tables[$tHandler]);
                            $this->assign_block_vars("row", array(
                                'BGCOLOR' => $this->_tables[$tHandler]['highlight_color']
                            ));
                        }
                        else
                        {
                            if (!empty($row['bg_color']))
                            {
                                $this->assign_block_vars("row", array(
                                    'BGCOLOR' => $row['bg_color']
                                ));
                            }
                            else
                            {
                                $this->assign_block_vars("row", array(
                                    'BGCOLOR' => $this->_tables[$tHandler]['colors'][$rowNum % count($this->_tables[$tHandler]['colors'])]
                                ));
                            }
                        }
                        if (isset($this->_tables[$tHandler]['groupBy']))
                        {
                            $group = isset($row[$this->_tables[$tHandler]['groupBy']]) ? substr($row[$this->_tables[$tHandler]['groupBy']], 0, 10) : "";

                            if ($group != $currentGroup)
                            {
                                //var_dump($group);
                                $this->override_last_delimiter(array('counter' => $currentGroupCounter));
                                $this->assign_block_vars("row.delimiter", array(
                                    'COLSPAN' => count($this->_tables[$tHandler]['fields']),
                                    'VALUE' => $group
                                ));
                                $currentGroup = $group;
                                $currentGroupCounter = 0;
                            }
                        }
                        if (isset($currentGroupCounter))
                        {
                            $currentGroupCounter++;
                        }
                        foreach ($this->_tables[$tHandler]['fields'] as $key => $field)
                        {
                            $val = $field['template'];
                            if (isset($field['quotes']))
                            {
                                $val = str_replace("\\'", "\"", $val);
                            }
                            if (isset($field['preexec']) && $field['preexec'])
                            {
                                if (isset($row[$field['name']]))
                                    $row[$field['name']] = str_replace("'", "\\'", $row[$field['name']]);
                                $code_str = "\$_v=" . $field['preexec'] . ";";
                                $code_str = str_replace("%", isset($row[$field['name']]) ? $row[$field['name']] : "", $code_str);
                                //var_dump($code_str);
                                eval($code_str);
                                $val = str_replace("%VALUE%", $_v, $val);
                            }
                            else
                            {
                                $val = str_replace("%VALUE%", isset($row[$field['name']]) ? $row[$field['name']] : "", $val);
                            }
                            $val = str_replace("%CHECKED%", (isset($row[$field['name']]) && $row[$field['name']] == 1) ? "checked" : "", $val);
                            foreach ($row as $k => $v)
                            {
                                $val = str_replace("%" . $k . "%", $v, $val);
                            }
                            $this->assign_block_vars("row.cell", array(
                                'VALUE' => $val,
                                'ALIGN' => isset($field['align']) ? $field['align'] : ""
                            ));
                        }
                        $rowNum++;
                    }
                    if (isset($currentGroupCounter))
                    {
                        $this->override_last_delimiter(array('counter' => $currentGroupCounter));
                    }
                }
                $code = eval($this->compile($tHandler));
                $this->assign_var(strtoupper($this->_tables[$tHandler]['handle']), $code);
                return true;
            }
        }
        else
        {
            return false;
        }
    }

    public function compile_all_tables()
    {
        if (isset($this->_tables) && is_array($this->_tables))
        {
            foreach ($this->_tables as $hTable => $table)
            {
                if (isset($table['fields']) && is_array($table['fields']))
                {
                    $this->compile_table($hTable);
                }
            }
        }
    }

}
