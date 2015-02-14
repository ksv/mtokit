<?php
class TemplateTagJavascript extends TemplateTagAbstract{
	
	
	
	public function compile($code)
	{

		$this -> replacement = "";
		if (empty($this->params['source'])) throw new pdException("template_compilation_error");
		$this -> tplobj -> add_included_js($this->params['source']);
		return $this -> replace($code);
	}
}
