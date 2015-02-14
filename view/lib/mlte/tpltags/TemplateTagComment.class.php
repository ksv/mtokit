<?php
class TemplateTagComment extends TemplateTagAbstract{
	
	public function compile($code)
	{
	    $this->replacement = "";
		return $this->replace($code);
	}
	
}
