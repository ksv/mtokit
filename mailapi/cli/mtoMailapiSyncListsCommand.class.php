<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/mailapi/mtoMailapiFactory.class.php");

class mtoMailapiSyncListsCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $conf = mtoConf :: instance()->loadConfig("mtokit/mailapi/config/mtokit_mailapi.ini");
        foreach (array_keys($conf->getSection("mailapi_providers")) as $provider)
        {
            $instance = mtoMailapiFactory :: create($provider, isset($args['dev']))->setDb(mtoToolkit :: instance()->getDbConnection());
            $data = $instance->loadLists();
            foreach ($data as $entry)
            {
                $instance->updateLocalList($entry);
            }
        }

    }

    function infoName()
    {
        return "mailapi:mailapi_sync_lists";
    }
}