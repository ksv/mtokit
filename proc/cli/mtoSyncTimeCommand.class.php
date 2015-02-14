<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoSyncTimeCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        exec('/usr/sbin/ntpdate -u 0.ru.pool.ntp.org 2>&1', $result);
        $this->out($result);
    }

    function infoTitle()
    {
        return "Sync system time";
    }

    function infoDescription()
    {
        return "Must execured as root";
    }
}