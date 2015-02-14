<?php
abstract class TemplateTagAbstract{
	
	protected $pattern = null;
	protected $encloser = null;
	protected $content = null;
	protected $params = array();
	protected $tagname = null;
	protected $replacement;
	protected $tplobj;
	protected $required = array();
	protected $optional = array();
	
	public function __construct(array $args, Template $instance)
	{
		$this -> tplobj = $instance;
		if (count($args) != 4) throw new pdException("template_compilation_error", array(__LINE__, __FILE__));
		$this -> init($args);
	}
	
	abstract function compile($str);
	
	protected function replace($str)
	{
		return str_replace($this->pattern, $this->replacement, $str);
	}
	
	public function init(array $args)
	{
		if (count($args) != 4) throw new pdException("template_compilation_error", array(__LINE__, __FILE__));
		$this -> pattern = $args[0];
		$this -> encloser = $args[3];
		$this -> tagname = $args[1];
		
		if (strpos($args[2], ">") !== false && $this->encloser != "/>")
		{
			list($params, $this->content) = explode(">", $args[2], 2);
			$this -> params = $this -> parse_params($params);
		}
		else
		{
			$this -> content = "";
			$this -> params = $this -> parse_params($args[2]);
		}
	}
	
	protected function parse_params($str)
	{
		$params = array();
		
		
		if (preg_match_all("#(\w+)=\"(.+)\"#smU", $str, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (count($match) == 3)
				{
					$params[$match[1]] = $this->tplobj->replace_tokens($match[2], false);
				}
			}
		}
		return $params;
	}
	
	protected function validate_required()
	{
	    foreach ($this->required as $req)
	    {
	        if (!in_array($req, array_keys($this->params))) throw new pdException("required_template_tag_param_missed");
	    }
	    return true;
	}
	
	protected function append_optional()
	{
	    foreach ($this -> optional as $opt => $default)
	    {
	        if (!isset($this->params[$opt])) $this->params[$opt] = $default;
	    }
	}
	
}
