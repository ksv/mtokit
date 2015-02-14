<?php
class TemplateTagIf extends TemplateTagAbstract{
	
    private $condition_replacement = array(
    		' eq ' => " == ",
    		' gt ' => " > ",
    		' lt ' => " < ",
    		' ne ' => " != "
    );
	
	
	public function compile($code)
	{
		$this -> content = str_replace("<tpl:else>", "\n<!-- PARSED -->\n}\nelse\n{\n<!-- /PARSED -->\n", $this->content);
		if (!isset($this -> params['condition'])) throw new pdException("template_compilation_error", array(__LINE__, __FILE__, $code));
		$this -> params['condition'] = str_replace(array_keys($this -> condition_replacement), array_values($this->condition_replacement), $this->params['condition']);
		$this -> replacement = "\n<!-- PARSED -->\nif (" . $this -> params['condition'] . ")\n{\n<!-- /PARSED -->" . $this -> content . "\n<!-- PARSED -->\n}\n<!-- /PARSED -->";
		return $this->replace($code);
	}
	
}
