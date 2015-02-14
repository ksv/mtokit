<?php
class TemplateTagLogged extends TemplateTagAbstract{
	
	public function compile($code)
	{
		if ($this->params['logged'] == 1)
		{
		    if (User :: createLogged()->get_id() > 0)
		    {
		        $this->replacement = $this->content;
		    }
		    else
		    {
		        $this->replacement = "";
		    }
		}
		else
		{
		    if (User :: createLogged()->get_id() > 0)
		    {
		        $this->replacement = "";
		    }
		    else
		    {
		        $this->replacement = $this->content;
		    }
		}
		return $this->replace($code);
	}
	
}
