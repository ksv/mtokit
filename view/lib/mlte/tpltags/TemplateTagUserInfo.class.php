<?php
class TemplateTagUserInfo extends TemplateTagAbstract{
	
	public function compile($code)
	{
        $user = User :: createLogged();
		$method = "get_" . $this->params['property'];
		$this->replacement = $user->$method();
		return $this->replace($code);
	}
	
}
