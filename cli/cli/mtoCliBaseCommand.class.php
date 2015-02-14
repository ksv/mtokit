<?php
class mtoCliBaseCommand
{
    protected $quiet_mode = false;
    protected $return_mode = false;
    protected $strings = array();


    function execute($args = array())
    {
        $this->out("Base command cant be called directly");
        die();
    }

    function quiet()
    {
        $this->quiet_mode = true;
    }

    function get_quiet()
    {
        return $this->quiet_mode;
    }
    
    function set_return()
    {
        $this->return_mode = true;
    }

    function out($string)
    {
        if ($this->quiet_mode)
        {
            return;
        }
        if (is_array($string))
        {
            foreach ($string as $str)
            {
                if ($this->return_mode)
                {
                    $this->strings[] = "***MTOKIT***   " . $str;
                }
                else
                {
                    echo "***MTOKIT***   " . $str . "\n";
                }
            }
        }
        else
        {
            if ($this->return_mode)
            {
                $this->strings[] = "***MTOKIT***   " . $string;
            }
            else
            {
                echo "***MTOKIT***   " . $string . "\n";
            }
        }
    }
    
    function get_strings()
    {
        return $this->strings;
    }

    function infoTitle()
    {
        return "Try to guess what this the shit";
    }

    function infoDescription()
    {
        return "Stupid author do not describe this";
    }

    function infoArguments()
    {
        return array();
    }

    function infoOptions()
    {
        return array();
    }

    function assertArgs($args)
    {
        $alist = $this->infoArguments();
        foreach ($alist as $aitem)
        {
            if (isset($args[$aitem['mapto']]))
            {
                continue;
            }
            if (!isset($args['arguments'][0]))
            {
                throw new mtoCliException($aitem['mapto'] . " argument is not defined for " . get_class($this));
            }
            $args[$aitem['mapto']] = array_shift($args['arguments']);
        }
        $olist = $this->infoOptions();
        foreach ($olist as $oitem)
        {
            if (!empty($oitem['default']))
            {
                if (!isset($args[$oitem['name']]))
                {
                    $args[$oitem['name']] = $oitem['default'];
                }
            }
            if (!empty($oitem['required']))
            {
                if (!isset($args[$oitem['name']]))
                {
                    throw new mtoCliException($oitem['name'] . " option is required for " . get_class($this));
                }
            }
        }
        return $args;
    }

    function usage()
    {
        $this->out("You should specify command to execute.");
        $this->out("You can try next pieces of text for example:");
        $this->out("");
        $this->out("mtocli.php list");
        $this->out("mtocli.php help command");
    }
}