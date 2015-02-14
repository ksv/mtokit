<?php
class TemplateTagImage extends TemplateTagAbstract
{
    protected $required = array("src", "width", "height");
    protected $optional = array("border" => 0, "class" => "", "alt" => "", "title" => "");
    
    public function compile($code)
    {
        $this -> validate_required();
        $this -> append_optional();
        $this -> replacement = "<img src='/index.php?mode=thumb&src={$this->params['src']}&type=image&w={$this->params['width']}&h={$this->params['height']}&option=".(isset($this->params['option']) ? $this->params['option'] : "")."&effect=".( isset($this->params['effect']) ? constant($this->params['effect']) : "" )."' alt='{$this->params['alt']}' title='{$this->params['title']}' width='{$this->params['width']}' height='{$this->params['height']}' class='{$this->params['class']}' border='{$this->params['border']}' />";
            //$this -> replacement = str_replace('\\', '\\\\', $this -> replacement);
            $this -> replacement = str_replace('\'', '\\\'', $this -> replacement);
        return $this->replace($code);
    }
    
    
    
}
?>