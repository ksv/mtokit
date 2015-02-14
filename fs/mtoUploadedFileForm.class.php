<?php
/**
 * Handle file uploads via regular form post (uses the $_FILES array)
 */

class mtoUploadedFileForm 
{
    
    protected $file_form_name = '';
    
    function __construct($file_form_name) 
    {
        $this->file_form_name = $file_form_name;
    }//
    
    
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
    function save($path) 
    {
        if(!move_uploaded_file($_FILES[$this->file_form_name]['tmp_name'], $path))
        {
            return false;
        }
        return true;
    }//
    
    function getName() 
    {
        return $_FILES[$this->file_form_name]['name'];
    }//
    
    function getSize() 
    {        
        return $_FILES[$this->file_form_name]['size'];
    }//
    
}