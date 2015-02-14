<?php

class mtoJsonServer
{

    protected $_args = array();    
    protected $_functions = array();
    protected $_classname = array();
    protected $_headers = array();

    /**
     * PHP's Magic Methods, these are ignored
     */
    protected  $_magic_methods = array(
        '__construct',
        '__destruct',
        '__get',
        '__set',
        '__call',
        '__sleep',
        '__wakeup',
        '__isset',
        '__unset',
        '__tostring',
        '__clone',
        '__set_state',
    );

    /**
     * Current Method
     */
    protected $_method;

    public function __construct()
    {
        
    }

   
    public function handle($request = false)
    {
        $this->_headers = array('Content-Type: application/json; charset=utf-8',"Access-Control-Allow-Origin: *");
        
        if (!$request) 
        {
            $request = $_REQUEST;
        }
        
        if (isset($request['method'])) 
        {
            $this->_method = $request['method'];
            if (isset($this->_functions[$this->_method])) 
            {                              
                $result = call_user_func_array($this->_classname, $request);
                
            } else 
            {
                $result = $this->fault(  new mtoException("Unknown Method '$this->_method'."),
                    404
                );
            }
        } 
        else 
       {
            
            $result = $this->fault(
                new mtoException("No Method Specified."),
                404
            );
        }
        
        
        foreach ($this->_headers as $header) 
        {
           header($header);
        
        }

        if (!empty($this->_args['callback']) && $this->isValidCallback($this->_args['callback']))
        {
            echo $this->_args['callback'].'(';
        }    
        
        echo json_encode($response);
            
        if (!empty($this->_args['callback']) && $this->isValidCallback($this->_args['callback']))
        {
            echo ')';
        }    
        
        return;
      
     }

    
    public function setClass($classname,  $argv = array())
    {
        $this->_args = $argv;
        $this->_classname = $classname;
        mtoClass::import($classsname.'.class.php');
        $methods = get_class_methods($classname);
        foreach ($methods as $method_name)
        {
            if (!in_array($method_name,$this->_magic_methods))
            {
                $this->_functions[] = $method_name;
            }        
        }    
        
        
        
    }

  
    public function getFunctions()
    {
        return $this->_functions;
    }
    
    public function fault(mtoException $exception, $code = null)
    {
        
        $result = array('message'=>$exception->getMessage());

        // Headers to send
        if ($code === null || (404 != $code)) 
        {
            $this->_headers[] = 'HTTP/1.0 400 Bad Request';
        } else {
            $this->_headers[] = 'HTTP/1.0 404 File Not Found';
        }

        return $result;
    }
    
    protected function isValidCallback($subject)
    {
        $identifier_syntax
          = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

        $reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
          'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
          'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
          'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
          'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
          'private', 'public', 'yield', 'interface', 'package', 'protected', 
          'static', 'null', 'true', 'false');

        return preg_match($identifier_syntax, $subject)
            && ! in_array(mb_strtolower($subject, 'UTF-8'), $reserved_words);
    }

  
 
}
