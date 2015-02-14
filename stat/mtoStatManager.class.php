<?php
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");

class mtoStatManager
{
    protected $db;
    
    use mtoSingletone;

    function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    function getCounter($project_id)
    {
        $code = array();
        $code[] = '<script type="text/javascript"><!--';
        $code[] = 'document.write("<a href=\'#\' "+';
        $code[] = '"target=_blank><img src=\'http://somehost.ru/hit?pid.'+$project_id+';ref"+';
        $code[] = 'escape(document.referrer)+((typeof(screen)=="undefined")?"":';
        $code[] = '";s"+screen.width+"x"+screen.height+"x"+(screen.colorDepth?';
        $code[] = 'screen.colorDepth:screen.pixelDepth))+";url"+escape(document.URL)+';
        $code[] = '";"+Math.random()+';
        $code[] = '"\' alt=\'\' title=\'xxx"+';
        $code[] = '" часа, посетителей за 24 часа и за сегодня\' "+';
        $code[] = '"border=0 width=88 height=31><\/a>");';
        $code[] = '</script>';

        return implode("\n", $code);


       
    }

}