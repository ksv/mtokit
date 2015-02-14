<?php

mtoClass::import('mtokit/fs/mtoUploadedFileXhr.class.php');
mtoClass::import('mtokit/fs/mtoUploadedFileUrl.class.php');
mtoClass::import('mtokit/fs/mtoUploadedFileForm.class.php');

class mtoFileUploader 
{
    private $allowedExtensions = array();
    private $sizeLimit = "20M";
    private $file;
    protected $file_form_name = '';
    protected $error_list = array();

    function __construct($file_form_name, array $allowedExtensions = array(), $sizeLimit = null)
    {        
        $this->file_form_name = $file_form_name;
        
        $allowedExtensions = array_map("strtolower", $allowedExtensions);
        
        $this->allowedExtensions = $allowedExtensions;       
        if ($sizeLimit)
        {    
            $this->sizeLimit = $sizeLimit;
        }    
        
        $this->sizeLimit = $this->toBytes($this->sizeLimit);
        
        //$this->checkServerSettings();       

        if (isset($_REQUEST[$this->file_form_name])) 
        {
            if ($this->file_form_name == 'upload_file') 
            {
                $this->file = new mtoUploadedFileXhr($this->file_form_name);
            }
            
            if ($this->file_form_name == 'import_url') 
            {
                $this->file = new mtoUploadedFileUrl($_REQUEST[$this->file_form_name]);
            } 
        }
        elseif (isset($_FILES[$this->file_form_name])) 
        {
            $this->file = new mtoUploadedFileForm($this->file_form_name);
        } else {
            $this->file = false; 
        }
    }
    
    private function checkServerSettings()
    {        
        $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));        

        
        if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit)
        {
            $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';             
            throw new mtoException('increase post_max_size and upload_max_filesize to '.$size);    
        }        
    }//
    
    private function toBytes($str)
    {
        $val = trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;        
        }
        return $val;
    }
    
    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
    function handleUpload($uploadFilename)
   {
        $uploadDirectory = dirname($uploadFilename);
        if (!is_dir($uploadDirectory))
        {
            mtoFs::mkdir($uploadDirectory);
        }    
        
        if (!is_writable($uploadDirectory))
        {
            $this->error_list[] = "Server error. Upload directory isn't writable.";
            return false;
        }
        
//        if (!$this->file)
//        {            
//            $this->error_list[] = "No files were uploaded.";
//            return false;
//        }
        
        $size = $this->file->getSize();
        
        if ($size == 0)             
        {            
            $this->error_list[] = "File is empty";
            return false;
        }
        
        
        if ($size > $this->sizeLimit) 
        {            
            $this->error_list[] = "File is too large";
            return false;
        }
        
        $pathinfo = pathinfo($this->file->getName());
        $filename = $pathinfo['filename'];
        //$filename = md5(uniqid());
        $ext = strtolower($pathinfo['extension']);
        

        if($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions))
        {
            $these = implode(', ', $this->allowedExtensions);
            //return array('error' => 'File has an invalid extension, it should be one of '. $these . '.');
            $this->error_list[] = "File has an invalid extension, it should be one of ". $these . '.';
            return false;
        }
        

        
        if ($this->file->save($uploadFilename.'.'.$ext))
        {
            return $uploadFilename.'.'.$ext;
            
        } else 
        {
            
            $this->error_list[] = 'Could not save uploaded file.' .
                    'The upload was cancelled, or server error encountered';            
            return false;
        }
        
    }//
    
    
    function getErrorList()
    {
        return $this->error_list;
    }
    
    function getOriginalFilename()
    {
        return $this->file->getName();
    }
}