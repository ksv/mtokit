<?php
class TemplateTagBlock extends TemplateTagAbstract{
	
    protected $required = array("show", "name");
    
	public function compile($code)
	{
	    $method = "block_" . $this->params['name'];
	    if (method_exists("Block", $method) && $this->params['show'] == "yes")
	    {
	        $this->replacement = Block :: $method($this->params);
	    }
	    else
	    {
	        $this->replacement = "";
	    }
		return $this->replace($code);
	}
	
}
