<?php
mtoClass :: import("mtokit/net/mtoRemoteRequest.class.php");
class mtoHttpUploadedFile
{
    public $fileInfo;
    protected $type;
    protected $tmpdir;

    function __construct($file = array(), $type = null)
    {
        $this->fileInfo = $file;
        $this->type = $type;
    }

    static function createUrl($url)
    {
        $url = filter_var($url, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE);
        $obj = new self(array(), "url");
        if (!empty($url))
        {
            try
            {
                $content = mtoRemoteRequest :: fetchCurl($url, array(), array('header' => 0, 'binarytransfer' => 1));
            }
            catch (mtoException $e)
            {
                $content = "";
            }
            if (!empty($content))
            {
                $obj->fileInfo['tmp_name'] = $obj->getTmpDir() . "/" . uniqid("f");
                file_put_contents($obj->fileInfo['tmp_name'], $content);
                $obj->fileInfo['error'] = 0;
                $obj->fileInfo['name'] = basename($url);
                $obj->fileInfo['size'] = filesize($obj->fileInfo['tmp_name']);
                if (strpos($obj->fileInfo['name'], "?") !== false)
                {
                    $parts = explode("?", $obj->fileInfo['name']);
                    $obj->fileInfo['name'] = $parts[0];
                }
            }
        }
        return $obj;
    }

    static function createMultipart($file)
    {
        $obj = new self($file, "multipart");
        return $obj;
    }

    static function createXhr($name)
    {
        $obj = new self(array(), "xhr");
        if (!empty($name))
        {
            $input = fopen("php://input", "r");
            $obj->fileInfo['tmp_name'] = $obj->getTmpDir() . "/" . uniqid("f");
            $tmp = fopen($obj->fileInfo['tmp_name'], "w");
            stream_copy_to_stream($input, $tmp);
            fclose($input);
            fclose($tmp);
            $obj->fileInfo['name'] = $name;
            $obj->fileInfo['size'] = filesize($obj->fileInfo['tmp_name']);
            $obj->fileInfo['error'] = file_exists($obj->fileInfo['tmp_name']) ? 0 : 1;
        }
        return $obj;
    }


    function getTmpDir()
    {
        if (empty($this->tmpdir))
        {
            $this->tmpdir = "var/tmp/" . uniqid("u");
        }
        if (!file_exists($this->tmpdir))
        {
            mtoFs :: mkdir($this->tmpdir);
        }
        return $this->tmpdir;
    }


    function isValid($validation = "none", $rules = array())
    {
        if (empty($this->fileInfo))
        {
            return false;
        }
        if (!empty($this->fileInfo['error']))
        {
            return false;
        }
        if (!empty($rules['exts']))
        {
            $info = pathinfo($this->getName());
            if (!in_array(strtolower($info['extension']), $rules['exts']))
            {
                return false;
            }
        }
        if (!mtoToolkit :: instance()->validateFile($this->fileInfo['tmp_name'], $validation, $rules))
        {
            return false;
        }
        return true;
    }

    function getName()
    {
        return $this->fileInfo['name'];
    }

    function getTmpName()
    {
        return $this->fileInfo['tmp_name'];
    }

    function save($target, $create_ext = false)
    {
        if ($create_ext)
        {
            $info = pathinfo($this->getName());
            $target .= "." . $info['extension'];
        }
        if ($this->type == "multipart")
        {
            move_uploaded_file($this->fileInfo['tmp_name'], $target);
        }
        else
        {
            rename($this->fileInfo['tmp_name'], $target);
        }
        return $target;
    }

    function handle($target, $create_ext = false, $validation = "none", $rules = array())
    {
        if ($this->isValid($validation, $rules))
        {
            $fname = $this->save($target, $create_ext);
            $this->destroy();
            return $fname;
        }
        else
        {
            $this->destroy();
            return false;
        }
    }

    function destroy()
    {
        if (!empty($this->tmpdir))
        {
            mtoFs :: rm($this->tmpdir);
        }
    }
}