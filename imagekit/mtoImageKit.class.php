<?php

class mtoImageKit
{

    static function create($library = 'gd', $dir = '', $params = array())
    {
        if (defined('LIMB_IMAGE_LIBRARY'))
            $library = LIMB_IMAGE_LIBRARY;

        $image_class_name = 'mto' . ucfirst(strtolower($library)) . 'ImageConvertor';

        $class_path = 'mtokit/imagekit/' . strtolower($library) . '/' . $image_class_name . '.class.php';

        mtoClass :: import($class_path);


        try
        {
            $convertor = new $image_class_name($params);
            
        }
        catch (mtoException $e)
        {
            throw new mtoFileNotFoundException($class_path, 'image library not found');
        }

        return $convertor;
    }

    static function load($file_name, $type = '', $library = 'gd', $dir = '', $params = array())
    {
        
        $convertor = self::create($library, $dir, $params);
        
        $convertor->load($file_name, $type);
        return $convertor;
    }

}
