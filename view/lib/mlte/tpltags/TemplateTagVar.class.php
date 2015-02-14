<?php
class TemplateTagVar extends TemplateTagAbstract{
	
		
	public function compile($code)
	{
		if (!isset($this->params['scope']) || !isset($this->params['name'])) throw new CoreException("template_compilation_error", CORE_FATAL, array(__LINE__, __FILE__));
		$this -> replacement = "";
		switch ($this -> params['scope'])
		{
			case "const":
				if (defined($this->params['name']))
				{
					$this -> replacement = constant($this->params['name']);
				}
			break;
			case "code":
				$varname = $this -> params['name'];
				global $$varname;
				if (isset($$varname))
				{
					$this -> replacement = $$varname;
				}
			break;
			case "template":
				$varname = $this -> params['name'];
				$tpl = Template::instance();
				if ($tpl -> get_data($varname))
				{
					$this -> replacement = $tpl -> get_data($varname);
				}
			break;
			case "env":
				if (empty($this -> params['target'])) $this -> params['target'] = "gprcseS";
				$var = "";
				$target = array();
				if (strpos($this->params['target'], "g") !== false) $target[] = "get";
				if (strpos($this->params['target'], "p") !== false) $target[] = "post";
				if (strpos($this->params['target'], "c") !== false) $target[] = "cookie";
				if (strpos($this->params['target'], "r") !== false) $target[] = "request";
				if (strpos($this->params['target'], "e") !== false) $target[] = "env";
				if (strpos($this->params['target'], "s") !== false) $target[] = "server";
				if (strpos($this->params['target'], "S") !== false) $target[] = "session";
                $this->replacement = mtoToolkit :: instance()->getRequest()->get($this->params['name']);
			break;
		}
	}

}

