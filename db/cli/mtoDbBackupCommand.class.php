<?php
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");

class mtoDbBackupCommand extends mtoCliBaseCommand
{
    function execute($args = array())
    {
        if (empty($args['base']))
        {
            throw new mtoCliException("Database not defined");
        }

        $filename = $args['base'] . "_" . date("Y-m-d_H-i");

        $command = "/usr/local/bin/mysqldump --quick --single-transaction";
        if (!empty($args['charset']))
        {
            $command .= " --default-character-set=" . $args['charset'];
        }
        if (!empty($args['host']))
        {
            $command .= " -h " . $args['host'];
        }
        if (!empty($args['port']))
        {
            $command .= " -P" . $args['port'];
        }
        if (isset($args['routines']))
        {
            $command .= " -R --triggers";
        }
        $command .= " -u ".$args['user']." -p" . $args['pwd'];
        if ($args['base'] == "all")
        {
            $command .= " --all-databases --master-data=1";
        }
        else
        {
            $command .= " " . $args['base'];
        }
        $command .= " > " . mtoConf :: instance()->get("core", "root") . "/backup/" . $filename . ".sql 2>&1";
        exec($command, $result);
        $this->out($command);
        $this->out($result);
        chdir(mtoConf :: instance()->get("core", "root") . "/backup/");
        exec("tar -jcf " . $filename . ".tar.bz2 " . $filename . ".sql", $result);
        $this->out($result);
        unlink($filename . ".sql");
        $this->out($args['base'] . " database dumped");
        chdir(mtoConf :: instance()->get("core", "root"));
    }

    function infoTitle()
    {
        return "Backup one or all mysql databases";
    }

    function infoDescription()
    {
        return "All databases will dumped with master data. Backup folder must exist in the project root";
    }

    function infoArguments()
    {
        return array(
            array('mapto' => "base", 'description' => "Name of the database or all keyword")
        );
    }

    function infoOptions()
    {
        return array(
            array('name' => "user", 'required' => true, 'description' => "mysql user"),
            array('name' => "pwd", 'required' => true, 'description' => "mysql password"),
            array('name' => "charset", 'description' => "Charset"),
            array('name' => "host", 'description' => "host to connect"),
            array('name' => "port", 'description' => "port to connect"),
            array('name' => "routines", 'single' => true, 'description' => "dump trigger")
        );
    }
}
