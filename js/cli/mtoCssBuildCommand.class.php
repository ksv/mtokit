<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/text/mtoCssMinimizer.class.php");

class mtoCssBuildCommand extends mtoCliBaseCommand
{
    
    function execute($args = array())
    {
        $conf = mtoConf :: instance();
        $minimizer = new mtoCssMinimizer();
        foreach ($conf->getSection("js") as $key => $value)
        {
            if (strpos($key, "css_pack") === 0)
            {
                $files = [];
                $html = file_get_contents($conf->getFilename($value));
                preg_match_all("#\<link.+rel=\"stylesheet\".+href=\"(.+)\".+/\>#U", $html, $matches, PREG_SET_ORDER);
                foreach ($matches as $match)
                {
                    if (strpos($match[1], '/assets/') === 0)
                    {
                        continue;
                    }
                    $files[] = substr(preg_replace("#\?.+$#", "", $match[1]), 1);
                }
                $minimizer->compressFiles($files, $conf->getFile("js.assets_path") . "/pack.".str_replace('css_pack_', '', $key).".css");
            }
        }




//        $adm = file_get_contents("template/admin/page.twig");
//        $front = file_get_contents("template/wrap.twig");
//        preg_match_all("#\<link.+href=\"(.+)\".+/\>#U", $adm, $adm_matches, PREG_SET_ORDER);
//        preg_match_all("#\<link.+rel=\"stylesheet\".+href=\"(.+)\".+/\>#U", $front, $front_matches, PREG_SET_ORDER);
        
//        $afiles = array();
//        $ffiles = array();
        
//        foreach ($adm_matches as $match)
//        {
//            if (strpos($match[1], '/assets/') === 0)
//            {
//                continue;
//            }
//            $afiles[] = substr(preg_replace("#\?.+$#", "", $match[1]), 1);
//        }
//        foreach ($front_matches as $match)
//        {
//            if (strpos($match[1], '/assets/') === 0)
//            {
//                continue;
//            }
//            $ffiles[] = substr(preg_replace("#\?.+$#", "", $match[1]), 1);
//        }
//        $minimizer = new mtoCssMinimizer();
//        $minimizer->compressFiles($afiles, "scripts/build/pack.admin.css");
//        $minimizer->compressFiles($ffiles, "scripts/build/pack.front.css");
    }

    function infoName()
    {
        return "js:css_build";
    }
    
    
    
}
