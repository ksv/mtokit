<?php

define('DEFAULT_COUNTRY', 2);
class mtoGeoFiasImport
{
    protected $path;    
    protected  $db;
    protected $toolkit;
    
    protected $has_kladr = false;
    protected $has_streets = false;
    protected $has_annotation = false;
    protected $has_altnames = false;
    protected $has_houses = false;
    protected $has_flat = false;
    protected $include_street_parents = array();
    
    const STREETS_ALL = 1;
    const STREETS_ONLY_CAPITAL = 2;

    
    protected $map = array(
        
    );
    
    function importAll()
    {
        //$this->importAnnotation();        
        $this->importLocation();
        //$this->importStreets();
        
        /*
        $this->importAltnames();
        
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
        
        
        
        $this->toolkit = mtoToolkit :: instance();
        $this->db = $this->toolkit->getDbConnection();
        
    }

    protected function importTable($table, $conn, $skip_update = false, $filter = null, $truncate = true)
    {
        if ($truncate)
        {    
            $this->db->execute("truncate table  ".$this->map[$table]['target']); 
        }    
        $length = dbase_numrecords($conn);
        for ($i=1; $i<=$length; $i++)
        {
            $row = dbase_get_record_with_names($conn, $i);
                       
            
            if ($filter != null)
            {
                if (!$this->$filter($row))
                {
                    continue;
                }
            }
            
            if ($i % 1000 == 0)
            {
                echo $i." rows imported\n";
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
                //echo ($sql);
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
                //echo ($sql);
                echo "Imported row\n";
            }
        }
        echo $table . " imported\n";
    }

   

    function importLocation()
    {
            
//        $conn = $this->openSource("ADDROBJ");
//        $this->importTable("ADDROBJ", $conn, true, 'filterLocation');
//        $this->closeSource($conn);
        
        //update locations
        $this->db->execute('UPDATE '.$this->map["ADDROBJ"]['target'].' set loc_country_id='.DEFAULT_COUNTRY );
        
        $all = $this->db->fetch('SELECT * from '.$this->map["ADDROBJ"]['target'].' where loc_parent_code<>""');
        foreach($all as $row)
        {
            $parent = $this->db->fetchOneRow("SELECT loc_id, loc_abbr, loc_level from ".$this->map["ADDROBJ"]['target']." where loc_code='".$row['loc_parent_code']."'");
            if (trim($parent['loc_abbr']) != 'г')
            {    
                $this->db->execute('UPDATE '.$this->map["ADDROBJ"]['target'].' set loc_parent_id="'.$parent['loc_id'].'" where loc_id='.$row['loc_id']);
            }    
        }    
        
        //update loc_has_kids
        $all = $this->db->fetch('SELECT distinct loc_parent_id  from '.$this->map["ADDROBJ"]['target']);
        foreach($all as $row)
        {
            
            $this->db->execute('UPDATE '.$this->map["ADDROBJ"]['target'].' set loc_has_kids="1" where loc_id='.(int)$row['loc_parent_id']);
        }
        
        //delete shit
        $this->db->execute('DELETE from '.$this->map["ADDROBJ"]['target'].' where (loc_parent_id is null or loc_parent_id=0) AND loc_level>1');
        
        echo "Locations  done\n";
        
    }//
    
    private function filterLocation($row)
    {
        if ($row['ACTSTATUS']<1)
        {
            return false;   
        }    
        
        if ($row['AOLEVEL']<=$this->map['ADDROBJ']['max_level'])
        {
            return true;
        }
        
        return false;
    }//
    
    private function filterSubMoscow($row)
    {
        if ($row['ACTSTATUS']<1)
        {
            return false;   
        }    
        
        if ($row['PARENTGUID']=='0c5b2444-70a0-4932-980c-b4dc0d3f02b5' && $row['AOLEVEL']<7)
        {
            return true;
        }
        
        return false;
    }//
    
    function importStreets()
    {
        
        //sub_moscow, sub_piter
        $rows = $this->db->fetch('SELECT loc_code from '.$this->map["ADDROBJ"]['target'].' where loc_parent_code in("0c5b2444-70a0-4932-980c-b4dc0d3f02b5","c2deb16a-0330-4f05-821f-1d09c93331e6")');
        
        foreach($rows as $row)
        {
            $this->include_street_parents[] = $row['loc_code'];
        }    
            
        $conn = $this->openSource("ADDROBJ");
        $this->importTable("STREET", $conn, true, 'filterStreet', false);
        $this->closeSource($conn);
        
        
        $all = $this->db->fetch('SELECT * from '.$this->map["STREET"]['target'].' where loc_parent_id is null and loc_level>1 ');
        foreach($all as $row)
        {
            $parent = $this->db->fetchOneValue("SELECT loc_id from ".$this->map["ADDROBJ"]['target']." where loc_code='".$row['loc_parent_code']."'");
            $this->db->execute('UPDATE '.$this->map["STREET"]['target'].' set loc_parent_id="'.$parent.'" where loc_id='.$row['loc_id']);
        }
       
        echo "Streets done\n";
        
    }//
    
     function importMoscow()
    {
        
        //sub_moscow, sub_piter
        $conn = $this->openSource("ADDROBJ");
        $this->importTable("ADDROBJ", $conn, true, 'filterSubMoscow', false);
        $this->closeSource($conn);
        
        $all = $this->db->fetch('SELECT * from '.$this->map["ADDROBJ"]['target'].' where loc_parent_id is null and loc_level>1 ');
        foreach($all as $row)
        {
            $parent = $this->db->fetchOneValue("SELECT loc_id from ".$this->map["ADDROBJ"]['target']." where loc_code='".$row['loc_parent_code']."'");
            $this->db->execute('UPDATE '.$this->map["ADDROBJ"]['target'].' set loc_parent_id="'.$parent.'" where loc_id='.$row['loc_id']);                
            $this->db->execute('UPDATE '.$this->map["ADDROBJ"]['target'].' set loc_has_kids="1" where loc_id='.(int)$parent);
        
        }
        
        
    }//
    
    
    private function filterStreet($row)
    {
        if ($row['ACTSTATUS']<1)
        {
            return false;   
        }    
        
        //7 - Street level
        if ($row['AOLEVEL']<>7)
        {
            return false;
        }    
        
        
        //moscow 0c5b2444-70a0-4932-980c-b4dc0d3f02b5
        //piter  c2deb16a-0330-4f05-821f-1d09c93331e6
        if ($row['PARENTGUID'] == '0c5b2444-70a0-4932-980c-b4dc0d3f02b5' || $row['PARENTGUID'] == 'c2deb16a-0330-4f05-821f-1d09c93331e6')
        {
            return true;
        }    

        if (in_array($row['PARENTGUID'], $this->include_street_parents))
        {
            return true;
        }        
        
        
        return false;
    }//
    
    
    
    
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

}