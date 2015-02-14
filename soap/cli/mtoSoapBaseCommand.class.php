<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
class mtoSoapBaseCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        parent :: execute($args);
    }

    function check_result($result, $skip_ok = false)
    {
        if (isset($result['status']))
        {
            if ($result['status'] == "done")
            {
                if (!$skip_ok)
                {
                    $this->out("DONE: " . $result['message']);
                }
            }
            else
            {
                throw new mtoCliException($result['message']);
            }
        }
        else
        {
            throw new mtoCliException("UNKNOWN SOAP RESULT");
        }
    }


}