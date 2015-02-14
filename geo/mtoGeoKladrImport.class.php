<?php

class mtoGeoKladrImport
{
    protected $path;
    protected $has_kladr = false;
    protected $has_streets = false;
    protected $has_annotation = false;
    protected $has_altnames = false;
    protected $has_houses = false;
    protected $has_flat = false;
    protected  $db;
    protected $toolkit;
    protected $config;
    
    const STREETS_ALL = 1;
    const STREETS_ONLY_CAPITAL = 2;

    protected $map = array(
        'SOCRBASE' => array(
            'target' => "geo_annotation",
            'identifier' => 'KOD_T_ST',
            'map' => array(
                'LEVEL' => "level",
                'SCNAME' => "abbr",
                'SOCRNAME' => "title",
                'KOD_T_ST' => "code"
            )
        ),
        'KLADR' => array(
            'target' => "tmp_kladr",
            'identifier' => "CODE",
            'map' => array(
                'NAME' => "title",
                'SOCR' => "abbr",
                'CODE' => "code",
                'INDEX' => "zip",
                'GNINMB' => "ufns",
                'UNO' => "uno",
                'OCATD' => "okato",
                'STATUS' => "status"
            )
        ),
        'ALTNAMES' => array(
            'target' => "geo_altnames",
            'identifier' => null,
            'map' => array(
                'OLDCODE' => "old_code",
                'NEWCODE' => "new_code",
                'LEVEL' => "level"
            ),
        ),
        'STREET' => array(
            'target' => "tmp_street",
            'identifier' => "CODE",
            'map' => array(
                'NAME' => "title",
                'SOCR' => "abbr",
                'CODE' => "code",
                'INDEX' => "zip",
                'GNINMB' => "ufns",
                'UNO' => "uno",
                'OCATD' => "okato",
            )
        ),
        'DOMA' => array(
            'target' => "tmp_house",
            'identifier' => "CODE",
            'map' => array(
                'NAME' => "title",
                'KORP' => "building",
                'SOCR' => "abbr",
                'CODE' => "code",
                'INDEX' => "zip",
                'GNINMB' => "ufns",
                'UNO' => "uno",
                'OCATD' => "okato",
            )
        ),
    );
    
    function importKladr()
    {
        $this->importAnnotation();        
        $this->importLocation();
        /*
        $this->importAltnames();
        $this->importStreets();
        $this->importHouses();
         * 
         */
    }

    //args['map']
    //args['path']
    //args['streets']
    //args['altnames']
    //args['houses']
    function __construct($args)
    {
        if (!empty($args['map']))
        {
            $this->map = $args['map'];
        }    
        
        $this->path = $args['path'];
        
        $this->has_kladr = file_exists($this->path . "/KLADR.DBF");
        if ($args['streets'])
        {    
            $this->has_streets = file_exists($this->path . "/STREET.DBF");
        }        
        $this->has_annotation = file_exists($this->path . "/SOCRBASE.DBF");
        if ($args['altnames'])
        {    
            $this->has_altnames = file_exists($this->path . "/ALTNAMES.DBF");
        }    
        if ($args['houses'])
        {    
            $this->has_houses = file_exists($this->path . "/DOMA.DBF");
        }    
        $this->has_flat = file_exists($this->path . "/FLAT.DBF");
        
        $this->toolkit = mtoToolkit :: instance();
        $this->db = $this->toolkit->getDbConnection();

        $this->config = mtoConf :: instance()->loadConfig("mtokit_geoip.ini")->getSection("geoip");
        
    }

    protected function importTable($table, $conn, $skip_update = false, $filter = null)
    {
        $this->db->execute("truncate table  ".$this->map[$table]['target']); 
        $length = dbase_numrecords($conn);
        for ($i=1; $i<=$length; $i++)
        {
            $row = dbase_get_record_with_names($conn, $i);
                       
            if ($i % 1000 == 0)
            {
                echo $i." rows imported\n";
            }
            if ($filter != null)
            {
                if (!$this->$filter($row))
                {
                    continue;
                }
            }
            $line = array();
            if ($skip_update)
            {
                $exists = false;
            }
            else
            {
                $exists = $this->db->fetchOneRow("select ".$this->map[$table]['pk']." from " . $this->map[$table]['target']." where " . $this->map[$table]['map'][$this->map[$table]['identifier']]."='".$row[$this->map[$table]['identifier']]."'");
            }
            if ($exists)
            {
                $pairs = array();
                foreach ($this->map[$table]['map'] as $k => $v)
                {
                    $pairs[] = $v."='".trim(mb_convert_encoding($row[$k], 'UTF-8', 'CP866'))."'";
                }
                $sql = "update " . $this->map[$table]['target'] . " set " . implode(", ", $pairs)." where " . $this->map[$table]['map'][$this->map[$table]['identifier']] . "='".$row[$this->map[$table]['identifier']]."'";
                $this->db->execute($sql);
            }
            else
            {
                $fields = array();
                $values = array();
                foreach ($this->map[$table]['map'] as $k => $v)
                {
                    $fields[] = $v;
                    $values[] = "'".trim(mb_convert_encoding($row[$k], 'UTF-8', 'CP866'))."'";
                }
                $sql = "insert into " . $this->map[$table]['target'] . "(" . implode(",", $fields) . ") values (" . implode(",", $values) . ")";
                $this->db->execute($sql);
            }
        }
        echo $table . " imported\n";
    }

    private function filterKladr($row)
    {
        return true;
        /*if (!preg_match("#^\d+00$#", $row['CODE']))
        {
            return false;
        }
        if (preg_match("#^\d\d0+$#", $row['CODE']) ||  trim(mb_convert_encoding($row['SOCR'], 'UTF-8', 'CP866')) == "г")
        {
            return true;
        }
        else
        {
            return false;
        }
         * 
         */
    }

    private function filterStreet($row)
    {
        
        /*static $codes = array();
        if (empty($codes))
        {
            $dataset = $this->db->fetch("select code from geo_location");
            foreach ($dataset as $r)
            {
                $codes[] = substr($r['code'], 0, 11);
            }
        }
        return in_array(substr($row['CODE'], 0, 11), $codes);
         * 
         */
    }

    private function filterHouse($row)
    {
        return false;
        /*static $codes = array();
        if (empty($codes))
        {
            $dataset = $this->db->fetch("select substr(code, 1, 15) as code from geo_street");
            foreach ($dataset as $r)
            {
                $codes[] = $r['code'];
            }
        }
        return in_array(substr($row['CODE'], 0, 15), $codes);
         * 
         */
    }

    function importAnnotation()
    {
       
        if (!$this->has_annotation)
        {
            return;
        }
        $conn = $this->openSource("SOCRBASE");
        $this->importTable("SOCRBASE", $conn);
        $this->closeSource($conn);
        $this->db->execute("DELETE t1 FROM ".$this->map['SOCRBASE']['target']." t1, ".$this->map['SOCRBASE']['target']."  t2 WHERE t1.an_abbr=t2.an_abbr AND t1.an_id > t2.an_id");
    }

    function importAltnames()
    {
        if (!$this->has_altnames)
        {
            return;
        }
        //$this->db->execute("truncate table geo_altnames");
        $conn = $this->openSource("ALTNAMES");
        $this->importTable("ALTNAMES", $conn, true);
        $this->closeSource($conn);
    }

    function importLocation()
    {
        if (!$this->has_kladr)
        {
            return;
        }
        $this->db->execute("create table tmp_kladr (loc_title varchar(255), loc_abbr varchar(10), loc_code varchar(13), loc_zip varchar(6), loc_ufns varchar(4), loc_uno varchar(4), loc_okato varchar(11), loc_status int) DEFAULT CHARSET=utf8");
        $conn = $this->openSource("KLADR");
        $this->importTable("KLADR", $conn, true, 'filterKladr');
        $this->closeSource($conn);
        
        $rows = $this->db->fetch("select * from tmp_kladr");
        $subjects = array();
        foreach ($rows as $row)
        {
            if (preg_match("#^(\d\d)0+$#", $row['loc_code'], $matches))
            {
                $exists = $this->db->fetchOneRow("select loc_id from ".$this->config['location_table']." where loc_code='".$row['loc_code']."'");
                if ($exists)
                {
                    $this->db->execute("update ".$this->config['location_table']." set loc_title='{$row['loc_title']}', abbr='{$row['loc_abbr']}', zip='{$row['loc_zip']}', ufns='{$row['loc_ufns']}', uno='{$row['loc_uno']}', okato='{$row['loc_okato']}', status='{$row['loc_status']}' where id='{$exists['loc_id']}'");
                    $subjects[$matches[1]] = $exists['loc_id'];
                }
                else
                {
                    $this->db->execute("insert into ".$this->config['location_table']." (loc_parent_id, loc_title, loc_abbr, loc_country_id, loc_code, loc_zip, loc_ufns, loc_uno, loc_okato, loc_status, loc_imported) values (0, '{$row['loc_title']}', '{$row['loc_abbr']}', '".DEFAULT_COUNTRY."', '{$row['loc_code']}', '{$row['loc_zip']}', '{$row['loc_ufns']}', '{$row['loc_uno']}', '{$row['loc_okato']}', '{$row['loc_status']}', 1)");
                    $subjects[$matches[1]] = $this->db->fetchOneValue("select last_insert_id()");
                }
            }
        }
        echo "SUBJECTS UPDATED\n";
        
        foreach ($rows as $row)
        {
            if (preg_match("#^(\d\d)\d+$#", $row['loc_code'], $matches) && !preg_match("#^\d\d0+$#", $row['loc_code']))
            {
                if (!preg_match("#^\d\d\d\d\d0+$#", $row['loc_code']))
                {
                    $exists = $this->db->fetchOneRow("select loc_id from ".$this->config['location_table']." where loc_code='".$row['loc_code']."'");
                    if ($exists)
                    {
                        $this->db->execute("update ".$this->config['location_table']." set loc_title='{$row['loc_title']}', loc_abbr='{$row['loc_abbr']}', zip='{$row['loc_zip']}', ufns='{$row['loc_ufns']}', uno='{$row['loc_uno']}', okato='{$row['loc_okato']}', status='{$row['loc_status']}' where id='{$exists['loc_id']}'");
                    }
                    else
                    {
                        $this->db->execute("insert into ".$this->config['location_table']." (loc_parent_id, loc_title, loc_abbr, loc_country_id, loc_code, loc_zip, loc_ufns, loc_uno, loc_okato, loc_status, loc_imported) values ('".$subjects[$matches[1]]."', '{$row['loc_title']}', '{$row['loc_abbr']}', '".DEFAULT_COUNTRY."', '{$row['loc_code']}', '{$row['loc_zip']}', '{$row['loc_ufns']}', '{$row['loc_uno']}', '{$row['loc_okato']}', '{$row['loc_status']}', 1)");
                    }
                }
            }
        }
        
        echo "CITIES updated\n";
        $this->db->execute("drop table tmp_kladr");
    }

    function importStreets()
    {
        if (!$this->has_streets)
        {
            return;
        }
        $this->db->execute("create table tmp_street (title varchar(255), abbr varchar(10), code varchar(17), zip varchar(6), ufns varchar(4), uno varchar(4), okato varchar(11), parent_id int, city_code varchar(13)) DEFAULT CHARSET=utf8");
        $conn = $this->openSource("STREET");
        $this->importTable("STREET", $conn, true, 'filterStreet');
        $this->closeSource($conn);
        $this->db->execute("update tmp_street set city_code = substr(code, 1, 11)");
        $this->db->execute("update tmp_street set parent_id = (select id from geo_location where substr(code, 1, 11)=city_code limit 1)");
        $this->db->execute("delete from tmp_street where parent_id is null");
        $rows = $this->db->fetch("select * from tmp_street where exists(select id from geo_street where code=tmp_street.code)");
        foreach ($rows as $row)
        {
            $this->db->execute("update geo_street set title='{$row['title']}', abbr='{$row['abbr']}', zip='{$row['zip']}' where code='{$row['code']}'");
        }
        $this->db->execute("insert into geo_street (location_id, title, abbr, code, zip, ufns, uno, okato, imported) select parent_id, title, abbr, code, zip, ufns, uno, okato, 1 from tmp_street where not exists(select id from geo_street where code=tmp_street.code)");
        $this->db->execute("drop table tmp_street");
    }
    
    function importHouses()
    {
        if (!$this->has_houses)
        {
            return;
        }
        $this->db->execute("create table tmp_house (title varchar(255), building varchar(10), abbr varchar(10), code varchar(19), zip varchar(6), ufns varchar(4), uno varchar(4), okato varchar(11), parent_id int, street_code varchar(15))");
        $conn = $this->openSource("DOMA");
        $this->importTable("DOMA", $conn, true, 'filterHouse');
        $this->closeSource($conn);
        $this->db->execute("update tmp_house set street_code = substr(code, 1, 15)");
        $this->db->execute("update tmp_house set parent_id = (select id from geo_street where substr(code, 1, 15)=street_code limit 1)");
        $this->db->execute("delete from tmp_house where parent_id is null");
        $rows = $this->db->fetch("select * from tmp_house where exists(select id from geo_house where code=tmp_house.code)");
        foreach ($rows as $row)
        {
            $this->db->execute("update geo_house set title='{$row['title']}', building='{$row['building']}', abbr='{$row['abbr']}', zip='{$row['zip']}' where code='{$row['code']}'");
        }
        $this->db->execute("insert into geo_house (street_id, title, building, abbr, code, zip, ufns, uno, okato, imported) select parent_id, title, building, abbr, code, zip, ufns, uno, okato, 1 from tmp_house where not exists(select id from geo_house where code=tmp_house.code)");
        $this->db->execute("drop table tmp_house");
    }

    protected function closeSource($conn)
    {
        if (!function_exists("dbase_close"))
        {
            throw new Exception("DBASE extension not available");
        }
        dbase_close($conn);
    }

    protected function openSource($table)
    {
        if (!function_exists("dbase_open"))
        {
            throw new Exception("DBASE extension not available");
        }
        $conn = dbase_open($this->path . "/" . $table.".DBF", 0);
        if ($conn === false)
        {
            throw new Exception("Unable to open " . $table . " table");
        }
        return $conn;
    }

    function removeSource()
    {
        /*if ($this->has_kladr)
        {
            unlink($this->path . "/KLADR.DBF");
        }
        if ($this->has_streets)
        {
            unlink($this->path . "/STREET.DBF");
        }
        if ($this->has_annotation)
        {
            unlink($this->path . "/SOCRBASE.DBF");
        }
        if ($this->has_altnames)
        {
            unlink($this->path . "/ALTNAMES.DBF");
        }
        if ($this->has_houses)
        {
            unlink($this->path . "/DOMA.DBF");
        }
        if ($this->has_flat)
        {
            unlink($this->path . "/FLAT.DBF");
        }
         * 
         */
    }
}