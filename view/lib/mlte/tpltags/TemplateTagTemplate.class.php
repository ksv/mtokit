<?php
class TemplateTagTemplate extends TemplateTagAbstract{
	
	
	
	public function compile($code)
	{
//            $code = str_replace('\\', '\\\\', $code);
//            $code = str_replace('\'', '\\\'', $code);
		if (empty($this -> params['file'])) throw new pdException("template_compilation_error", array(__LINE__, __FILE__));
		$handle = uniqid('tpl');
		$this -> tplobj -> set_filenames(array($handle => $this->params['file']));
		$this -> replacement = $this -> tplobj -> pparse($handle, true);
            $this -> replacement = str_replace('\\', '\\\\', $this -> replacement);
            $this -> replacement = str_replace('\'', '\\\'', $this -> replacement);
		
		return $this->replace($code);
	}
}
