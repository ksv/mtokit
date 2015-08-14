<?php
/**
 * --login=client_login
 * --password=client_password
 */
mtoClass :: import("mtokit/soap/cli/mtoSoapBaseCommand.class.php");
mtoClass :: import("mtokit/dfs/mtoDfsManager.class.php");

class mtoDfsRegisterCommand extends mtoSoapBaseCommand
{
    function execute($args = array())
    {
        if (empty($args['login']) || empty($args['password']))
        {
            throw new mtoCliException("Login and password are required");
        }
        try
        {
            $result = mtoDfsManager :: create()->register($args['login'], $args['password']);
            $this->check_result($result);
        }
        catch (Exception $e)
        {
            throw new mtoCliException($e->getMessage());
        }


    }

    function infoName()
    {
        return "dfs:register";
    }

    function infoTitle()
    {
        return "Register dfs client";
    }

    function infoOptions()
    {
        return array(
            ['name' => "login", 'required' => true, 'description' => "Login"],
            ['name' => "password", 'required' => true, 'description' => "Password"]
        );
    }
}