<?php

mtoClass :: import('mtokit/ao/mtoActiveObject.class.php');
mtoClass :: import('mtokit/ao/mtoTaggableModel.class.php');

abstract class mtoLocalizableModel extends mtoTaggableModel
{
    const DEFAULT_LANG = 'ru';   
    protected $_current_lang = 'ru';
    protected $_external_objects = array();
    
    
    public function __construct($id = 0, $eager = false, $lang=null) 
    {
        parent::__construct($id, $eager);
        if (!empty($lang))
        {
            $this->setLang($lang);
        } else
        {    
            $this->detectLang();
        }    
    }
    
    function setLang($lang)
    {
        if (in_array($lang, $GLOBALS['available_langs']))
        {        
            $this->_current_lang = $lang;            
        } else
        {
            throw new pdException('Lang '.$lang.' is not supported');
        }    
    }
    
    private function detectLang()
    {
                
        if (!empty($_SESSION[$this->_model_name.'_lang']))
        {
            $this->setLang($_SESSION[$this->_model_name.'_lang']);
        }
        else if (!empty($_SESSION['lang']))
        {
            
            $this->setLang($_SESSION['lang']);
        }
        else
        {
            $this->_current_lang = self::DEFAULT_LANG;
        }    
    }
    
    function __call($method, $args = array()) 
    {
        
        if($property = $this->_mapGetToProperty($method))
        {
            //check if property is i18n or is default lang            
            if ($this->_current_lang == self::DEFAULT_LANG || !$this->isI18nProperty($property))
            {
                ///
                parent::__call($method,$args);                 
                
             }  else
             {
                 //get external
                 $this->_getExternalValue($property);
                 
             }            
        }
        elseif($property = $this->_mapSetToProperty($method))
        {
            //check if property is i18n or is default lang
            if ($this->_current_lang == self::DEFAULT_LANG || !$this->isI18nProperty($property))
            {
               parent::set($property, $args[0]);

            } 
            else 
            {  
                $this->_setExternalValue($property, $args[0]);

            }    
            return;
        }
        
    }//
    
    
    function get($property, $default = null)
    {
        if ($this->_current_lang == self::DEFAULT_LANG || !$this->isI18nProperty($property))
        {
            return parent::get($property, $default);            
        } else
        {
            //get external value
            return $this->_getExternalValue($property);
            
        }    
    }
    
    function set($property, $value)
    {
        if ($this->_current_lang == self::DEFAULT_LANG || !$this->isI18nProperty($property))
        {
            return parent::set($property, $value);            
        } else
        {
            //set external value
            $this->_setExternalValue($property, $value);
        }    
    }    
    
    
    protected function _onAfterSave() 
    {
        
        foreach($this->_external_objects as $prop=>$obj)
        {
            $conf = $this->getColumnConfig($prop);
            $obj->set($conf['rel_obj_field'],$this->getId());
            $obj->save();
        }    
    }
    
    
    private function isI18nProperty($property)
    {
        if (!empty($this->_prefix))
        {
            if (strpos($property, $this->_prefix) === false)
            {        
                $property = $this->_prefix.'_'.$property;            
            }    
        }        
        
        if (isset($this->_config['columns'][$property]))
        {
            $conf = $this->getColumnConfig($property);
            if (strpos($conf['type'], 'i18n') !== false)
            {
                return true;
            }        
        }    
    }
    
    private function _setExternalValue($property,$value)
    {
        if (!empty($this->_prefix))
        {
            if (strpos($property, $this->_prefix) === false)
            { 
                $property = $this->_prefix.'_'.$property;            
            }   
        }
       
         $refObj = $this->_getPropertyRelObject($property);
         $conf = $this->getColumnConfig($property);
         if (is_object($refObj))
         {
             $refObj->set($conf['rel_content_field'],$value);
             
         } 
         
         //return $refObj->save();
    }
    
    private function _getExternalValue($property)
    {
        if (!empty($this->_prefix))
        {
            if (strpos($property, $this->_prefix) === false)
            { 
                $property = $this->_prefix.'_'.$property;            
            }   
        }
       
         $refObj = $this->_getPropertyRelObject($property);
         $conf = $this->getColumnConfig($property);                  
         return $refObj->get($conf['rel_content_field']);
         
             
    }
    
    private function _getPropertyRelObject($property)
    {
        
        if (!empty($this->_external_objects[$property]) && is_object($this->_external_objects[$property]))
        {
            
            return $this->_external_objects[$property];
        }        
         $conf = $this->getColumnConfig($property);
         $obj_name = $conf['rel_model'];
         mtoClass :: import('classes/model/'.mto_camel_case($obj_name).'.class.php');
        
         if (!$this->isNew())
         {    
            $refObj = call_user_func(array(mto_camel_case($obj_name), "findFirst"), 
                                      array('condition'=>array($conf['rel_lang_field'] .' = "'.$this->_current_lang.'"' ,
                                                               $conf['rel_model_field'] .' = "'.$this->_model_name.'"',
                                                               $conf['rel_obj_field'] .' = "'.$this->getId().'"',
                                                               $conf['rel_field_field'] .' = "'.$property.'"'
                                                               )
                                             )
                                        );
         }
         
         if ($this->isNew() || !is_object($refObj))
         {
             $class_name = mto_camel_case($obj_name);
             $refObj = new $class_name();
             $refObj->set($conf['rel_lang_field'], $this->_current_lang );
             $refObj->set($conf['rel_model_field'], $this->_model_name);
             $refObj->set($conf['rel_obj_field'], $this->getId());
             $refObj->set($conf['rel_field_field'], $property);
         }    
         
         $this->_external_objects[$property] = $refObj;         
         return $refObj;
    }
    
    

}
