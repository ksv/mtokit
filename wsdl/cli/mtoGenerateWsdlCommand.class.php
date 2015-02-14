<?php
/**
 * --wsdl=name_of_wsdl_config_section
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoGenerateWsdlCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        if (empty($args['wsdl']))
        {
            throw new mtoCliException("Wsdl name not defined");
        }
        mtoConf :: instance()->loadConfig("mtowsdl.ini");
        $config = mtoConf :: instance()->getSection("wsdl_" . $args['wsdl']);
        if (empty($config))
        {
            throw new mtoCliException("WSDL definition not cound");
        }
        if (empty($config['name']) || empty($config['url']) || empty($config['classes']) || empty($config['out']))
        {
            throw new mtoCliException("WSDL definition is incorrect");
        }
        mtoClass :: import("mtokit/wsdl/mtoWSDLCreator.class.php");
        $wsdl = new mtoWSDLCreator($config['name'], $config['url']);
        $classdefs = explode(",", $config['classes']);
        foreach ($classdefs as $classdef)
        {
            list($class, $path, $url) = explode("|", $classdef);
            $wsdl->addFile(mtoConf :: instance()->getFilename($path."/".$class.".class.php"));
            $wsdl->addURLToClass($class, $url);
        }
        $wsdl->createWSDL();
        $path = mtoConf :: instance()->getFilename($config['out'] . "/" . $config['name'] . ".wsdl");
        if (!file_exists(dirname($path)))
        {
            mtoFs :: mkdir(dirname($path));
        }
        $wsdl->saveWSDL($path, true);
    }
}