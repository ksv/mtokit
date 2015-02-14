<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoCliCommandHelpCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        $list = mtoApp :: getConf()->get("cli", "commands");
        if (!isset($list[$args['command']]))
        {
            throw new mtoCliException("Command " . $args['command'] . " required");
        }
        $cmd = mtoCli :: createCommand($args['command']);
        $this->out(mtoCli :: bold($args['command']) . " - " . mtoCli :: bold(mtoCli :: green($cmd->infoTitle())));
        $this->out($cmd->infoDescription());
        $this->out("");
        if (count($cmd->infoArguments()) > 0)
        {
            $this->out("Arguments:");
            foreach ($cmd->infoArguments() as $arg)
            {
                $this->out(mtoCli :: bold($arg['mapto']) . " - " . $arg['description']);
            }
            $this->out("");
        }
        if (count($cmd->infoOptions()) > 0)
        {
            $this->out("Options:");
            foreach ($cmd->infoOptions() as $opt)
            {
                $addstr = "=val";
                if (!empty($opt['single']))
                {
                    $addstr = "";
                }
                if (!empty($opt['required']))
                {
                    $optstr = "--" . $opt['name'] . $addstr;
                }
                else
                {
                    $optstr = "[--" . $opt['name'] . $addstr . "]";
                }
                $this->out(mtoCli :: bold($optstr) . " - " . $opt['description'] . (!empty($opt['default']) ? ("(Default: " . $opt['default'] . ")") : ""));
            }
            $this->out("");
        }
        $this->out("Usage:");
        $exstr = "./mtocli.php " . $args['command'] . " ";
        foreach ($cmd->infoArguments() as $arg)
        {
            $exstr .= $arg['mapto'] . " ";
        }
        foreach ($cmd->infoOptions() as $opt)
        {
            $addstr = !empty($opt['single']) ? "" : "=" . (!empty($opt['default']) ? $opt['default'] : "val");
            $optstr = !empty($opt['required']) ? ("--" . $opt['name'] . $addstr) : ("[--" . $opt['name'] . $addstr . "]");
            $exstr .= $optstr;
        }
        $this->out(mtoCli :: red($exstr));

        $this->out("");
        $this->out("");
    }

    function infoTitle()
    {
        return "Output command description";
    }

    function infoArguments()
    {
        return array(
            array('mapto' => "command", "description" => "Name of command")
        );
    }
}
