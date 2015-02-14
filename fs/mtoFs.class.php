<?php
mtoClass :: import("mtokit/fs/mtoFsException.class.php");

class mtoFs
{
    static function safeExists($file)
    {
        return @fclose(@fopen($file, "r"));
    }
    
    
    static function safeWrite($file, $content, $perm=0664)
    {
        self :: mkdir(dirname($file));

        $tmp = self :: generateTmpFile('_');
        $fh = fopen($tmp, 'w');

        if ($fh === false)
        {
            @unlink($tmp);
            throw new mtoFsException('could not open file for writing', array('file' => $file));
        }

        @flock($fh, LOCK_EX);
        fwrite($fh, $content);
        @flock($fh, LOCK_UN);
        fclose($fh);

        if (file_exists($file))
        {
            @unlink($file);
        }

        if (!@rename($tmp, $file))
        {
            @unlink($tmp);
            throw new mtoFsException('could not move file', array('src' => $tmp, 'file' => $file));
        }

        @chmod($file, $perm);
        if (file_exists($tmp))
        {
            @unlink($tmp);
        }
    }

    static function mkdir($dir, $perm=0777, $parents=true)
    {
        if (!$dir)
        {
            throw new mtoFsException('Directory have no value');
        }

        if (is_dir($dir))
        {
            return;
        }

        //$dir = self :: normalizePath($dir);

        if (!$parents)
        {
            self :: _doMkdir($dir, $perm);
            return;
        }

        $path_elements = explode("/", $dir);
        $dirs = explode("/", $dir);

        if (count($path_elements) == 0)
        {
            return;
        }

        $path="";
        for ($i=0; $i<=count($path_elements); $i++)
        {
            $path .= array_shift($dirs) . "/";
            self :: _doMkdir($path, $perm);
        }

    }

    protected static function _doMkdir($dir, $perm)
    {
        if (is_dir($dir))
        {
            return;
        }

        $oldumask = umask(0);
        if (!@mkdir($dir, $perm))
        {
            umask($oldumask);
            throw new mtoFsException('failed to create directory', array('dir' => $dir));
        }

        umask($oldumask);
    }

    static function getTmpDir()
    {
        if ($path = mtoConf :: instance()->getFile("core", "vardir"))
        {
            return $path;
        }

        if ($path = session_save_path())
        {
            if (($pos = strpos($path, ';')) !== false)
                $path = substr($path, $pos + 1);
            return $path;
        }

        if ($tmp = getenv('TMP') || $tmp = getenv('TEMP') || $tmp = getenv('TMPDIR'))
        {
            return $tmp;
        }

        return '/tmp';
    }

    static function generateTmpFile($prefix = 'p')
    {
        return tempnam(self :: getTmpDir(), $prefix);
    }

    static function rm($file)
    {
        if (!file_exists($file))
        {
            return false;
        }
        if (!is_dir($file))
        {
            if (!@unlink($file))
            {
                throw new mtoFsException('failed to remove file', array('file' => $file));
            }
            return;
        }
        if (!$handle = @opendir($file))
        {
            throw new mtoFsException('failed to open directory', array('dir' => $file));
        }
        while (($f = readdir($handle)) !== false)
        {
            if ($f == '.' || $f == '..')
                continue;

            self :: rm($file . '/' . $f);
        }
        closedir($handle);

        if (!@rmdir($file))
        {
            throw new mtoFsException('failed to remove directory', array('dir' => $file));
        }

        clearstatcache();

        return true;
    }

    static function mv($src, $dest)
    {
        if (is_dir($src) || is_file($src))
        {
            if (!@rename($src, $dest))
            {
                throw new mtoFsException('failed to move item', array('src' => $src, 'dest' => $dest));
            }

            clearstatcache();
        }
        else
        {
            throw new mtoFsException('source file or directory does not exist', array('src' => $src));
        }
    }
    
    static function rcp($src, $dest)
    {
        mtoClass :: import("mtokit/net/mtoRemoteRequest.class.php");
        if (!file_exists(dirname($dest)))
        {
            self :: mkdir(dirname($dest));
        }
        
        $fp = fopen($dest, "w");
        try
        {
            mtoRemoteRequest :: fetchCurl($src, array(), array(
                'returntransfer' => false,
                'file' => $fp
            ));
            fclose($fp);
            return true;
        }
        catch (mtoException $e)
        {
            fclose($fp);
            unlink($dest);
            return false;
        }
    }

    static function cp($src, $dest, $exclude_regex = '', $include_regex = '', $include_hidden = true)
    {
        if (!is_dir($src))
        {
            if (!is_dir($dest))
            {
                self :: mkdir(dirname($dest));
            }
            else
            {
                $dest = $dest . '/' . basename($src);
            }

            if (@copy($src, $dest) === false)
            {
                throw new mtoFsException('failed to copy file', array('src' => $src, 'dest' => $dest));
            }
            return;
        }

        self :: mkdir($dest);

        $items = self :: find($src, 'df', $include_regex, $exclude_regex, false, $include_hidden);

        $total_items = $items;
        while (count($items) > 0)
        {
            $current_items = $items;
            $items = array();
            foreach ($current_items as $item)
            {
                $full_path = $src . '/' . $item;
                if (is_file($full_path))
                {
                    copy($full_path, $dest . '/' . $item);
                }
                elseif (is_dir($full_path))
                {
                    self :: _doMkdir($dest . '/' . $item, 0777);

                    $new_items = self :: find($full_path, 'df', $include_regex, $exclude_regex, $item, $include_hidden);

                    $items = array_merge($items, $new_items);
                    $total_items = array_merge($total_items, $new_items);

                    unset($new_items);
                }
            }
        }
        if ($total_items)
        {
            clearstatcache();
        }

        return $total_items;
    }

    static function ls($path, $types = "dfl", $add_path = false)
    {
        if (!is_dir($path))
        {
            return array();
        }

        $files = array();
        if ($handle = opendir($path))
        {
            while (($file = readdir($handle)) !== false)
            {
                if ($file != '.' && $file != '..')
                {
                    $fullfile = $path . "/" . $file;
                    if (is_dir($fullfile) && strpos($types, "d") === false)
                    {
                        continue;
                    }
                    if (is_file($fullfile) && strpos($types, "f") === false)
                    {
                        continue;
                    }
                    if (is_link($fullfile) && strpos($types, "l") === false)
                    {
                        continue;
                    }
                    if ($add_path)
                    {
                        $files[] = $fullfile;
                    }
                    else
                    {
                        $files[] = $file;
                    }
                }
            }
            closedir($handle);
        }
        return $files;
    }

    static function find($dir, $types = 'dfl', $include_regex = '', $exclude_regex = '', $add_path = true, $include_hidden = false)
    {
        $dir = self :: chop($dir);
        $items = array();

        if ($handle = @opendir($dir))
        {
            while (($element = readdir($handle)) !== false)
            {
                if ($element == '.' || $element == '..')
                    continue;
                if (!$include_hidden && $element[0] == '.')
                    continue;
                if ($include_regex && !preg_match($include_regex, $element, $m))
                    continue;
                if ($exclude_regex && preg_match($exclude_regex, $element, $m))
                    continue;
                if (is_dir($dir . "/" . $element) && strpos($types, 'd') === false)
                    continue;
                if (is_link($dir . "/" . $element) && strpos($types, 'l') === false)
                    continue;
                if (is_file($dir . "/" . $element) && strpos($types, 'f') === false)
                    continue;

                if ($add_path)
                {
                    if (is_string($add_path))
                        $items[] = $add_path . "/" . $element;
                    else
                        $items[] = $dir . "/" . $element;
                }
                else
                    $items[] = $element;
            }
            closedir($handle);
        }
        sort($items);
        return $items;
    }

    static function findRecursive($path, $types = 'dfl', $include_regex = '', $exclude_regex = '', $add_path = true, $include_hidden = false)
    {
        return self :: walkDir($path,
                array('mtoFs', '_doFindRecursive'),
                array('types' => $types,
                    'include_regex' => $include_regex,
                    'exclude_regex' => $exclude_regex,
                    'add_path' => $add_path,
                    'include_hidden' => $include_hidden),
                true);
    }

    protected static function _doFindRecursive($dir, $file, $path, $params, &$return_params)
    {
        if (!is_dir($path))
            return;

        $items = self :: find($path,
                        $params['types'],
                        $params['include_regex'],
                        $params['exclude_regex'],
                        $params['add_path'],
                        $params['include_hidden']);
        foreach ($items as $item)
        {
            $return_params[] = $item;
        }
    }

    static function walkDir($dir, $function_def, $params=array(), $include_first=false)
    {
        $return_params = array();

        $dir = self :: chop($dir);


        self :: _doWalkDir($dir,
                        $function_def,
                        $return_params,
                        $params,
                        $include_first);

        return $return_params;
    }

    protected static function _doWalkDir($item, $function_def, &$return_params, $params, $include_first, $level=0)
    {
        if ($level > 0 || ($level == 0 && $include_first))
            call_user_func_array($function_def, array('dir' => dirname($item),
                'file' => basename($item),
                'path' => $item,
                'params' => $params,
                'return_params' => &$return_params));
        if (!is_dir($item))
        {
            return;
        }

        $handle = opendir($item);

        while (($file = readdir($handle)) !== false)
        {
            if (($file == '.') || ($file == '..'))
                continue;

            self :: _doWalkDir($item . "/" . $file,
                            $function_def,
                            $return_params,
                            $params,
                            $level + 1);
        }
        closedir($handle);
    }

    static function chop($path)
    {
        if (substr($path, -1) == '/')
        {
            $path = substr($path, 0, -1);
        }

        return $path;
    }

    static function normalize($filename)
    {
        $filename = preg_replace("#\/{2,}#", "/", $filename);
        $filename = preg_replace("#\/+$#", "", $filename);
        return $filename;
    }

}