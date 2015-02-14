<?php
class TemplateTagInsertJavascript extends TemplateTagAbstract{
	
	
	
	public function compile($code)
	{

		$str = "";
		$js = $this -> tplobj -> get_included_js();
		if (is_array($js) && count($js))
		{
			foreach ($js as $file)
			{
				$str .= "<script type=\"text/javascript\" src=\"$file?rnd=".mtoConf :: instance()->get("core", "version")."\"></script>\n";
			}
		}
		$this -> replacement = $str;
		return $this -> replace($code);
	}
}
