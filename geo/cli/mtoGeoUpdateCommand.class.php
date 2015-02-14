<?php
/**
 */
mtoClass :: import("mtokit/cli/cli/mtoCliBaseCommand.class.php");
mtoClass :: import("mtokit/geo/toolkit/mtoGeoTools.class.php");

class mtoGeoUpdateCommand extends mtoCliBaseCommand
{
    
    
    const NGINX_PATH = "/usr/local/etc/nginx";
    function execute($args = array())
    {
        $replacement = array(
            'Зеленоград' => GeoLocation :: MOSCOW,
            'Щербинка' => GeoLocation :: MOSCOW,
            'Аэропорт "Домодедово"' => GeoLocation :: MOSCOW,
            'Пулково' => GeoLocation :: PITER,
            'Совхоз имени Ленина' => GeoLocation :: MOSCOW,
            'п. Лесной Городок' => GeoLocation :: MOSCOW,
            'Адлер' => 32624,
            'Сестрорецк' => GeoLocation :: PITER,
            'Усть-Кинельский' => 138512,
            'Старая Чара' => 90566,
            'пос. Вешки' => GeoLocation :: MOSCOW,
            'пос. Лесной' => GeoLocation :: MOSCOW,
            'Ерофей-Павлович' => 33189,
            'Купавна' => GeoLocation :: MOSCOW,
            'Троицк' => 18454
        );
        
        
        
        $confObj = mtoConf :: instance();
        $conf = $confObj->getSection("geoip");
        if (empty($conf))
        {
            $confObj->loadConfig("mtokit_geoip.ini");
        }
        $conf = $confObj->getSection("geoip");
        
        mtoToolkit :: merge(new mtoGeoTools());
        
        mtoFs :: mkdir("var/geo");
        $this->out("uploaded");
        mtoFs :: rcp($conf['geoip_uri'], "var/geo/uploaded.tar.gz");
        chdir("var/geo");
        exec("tar -zxf uploaded.tar.gz 2>&1", $res);
        $this->out("unpack");
        $this->out($res);
        chdir($confObj->get("core", "root"));
        
        $city_list = array();
        $region_list = array();
        $regions = array();
        $list = file("var/geo/cities.txt");
        foreach ($list as $elem)
        {
            if ($elem)
            {
                $parts = explode("\t", $elem);
                $city_list[$parts[0]] = mb_convert_encoding($parts[1], 'UTF-8', 'Windows-1251');
                $r = mb_convert_encoding($parts[2], 'UTF-8', 'Windows-1251');
                $r = str_replace(" область", "", $r);
                $r = str_replace(" край", "", $r);
                $r = str_replace("Республика ", "", $r);
                $r = str_replace(" автономная", "", $r);
                $r = str_replace(" автономный округ", "", $r);
                $r = str_replace(" (Тува)", "", $r);
                $r = str_replace("Чечня", "Чеченская", $r);
                $r = str_replace("Удмуртия", "Удмуртская", $r);
                $r = str_replace("Северная Осетия (Алания)", "Северная Осетия - Алания", $r);
                $r = str_replace("Чувашия", "Чувашская", $r);
                $r = str_replace("Кабардино-Балкария", "Кабардино-Балкарская", $r);
                $r = str_replace("Карачаево-Черкессия", "Карачаево-Черкесская", $r);
                if (strpos($r, 'Якутия') !== false)
                {
                    $r = "Саха /Якутия/";
                }
                $r = trim($r);
                if (!isset($regions[$r]))
                {
                    $row = mtoDb :: fetchOneRow("select * from " . $conf['location_table'] . " where loc_title='".$r."' and loc_parent_id is null and loc_fake=0 and loc_has_kids=1");
                    if (!$row)
                    {
                        var_dump("NOT FOUND: " . $r);
                    }
                    $regions[$r] = $row;
                }
                $region_list[$parts[0]] = substr($regions[$r]['loc_kladr_code'], 0, 2);
                if (empty($region_list[$parts[0]]))
                {
                    if ($city_list[$parts[0]] == "Москва")
                    {
                        $region_list[$parts[0]] = 77;
                    }
                    if ($city_list[$parts[0]] == "Санкт-Петербург")
                    {
                        $region_list[$parts[0]] = 78;
                    }
                }
            }
        }
        $this->out("cities parsed");
        
        mtoDb :: execute("truncate table " . $conf['raw_table']);
        $source = file("var/geo/cidr_optim.txt");
        $target = array();
        foreach ($source as $line)
        {
            $line = trim($line);
            $parts = explode("\t", $line);
            $range = explode(" - ", $parts[2]);
            $net = mtoToolkit :: instance()->ip2cidr($range[0], $range[1]);
            $country = $parts[3];
            if (trim($country) == "RU")
            {
                if (isset($city_list[$parts[4]]))
                {
                    $city = $city_list[$parts[4]];
                    $code = $region_list[$parts[4]];
                    mtoDb :: execute("replace into ".$conf['raw_table']." (segm_start, segm_istart, segm_end, segm_iend, segm_addr, city_title, city_code) values ('".$range[0]."', '".intval($parts[0])."', '".$range[1]."', '".intval($parts[1])."', '".$net."', '".$city."', '".$code."')");
                }
            }
        }
        mtoDb :: execute("update ".$conf['raw_table']." set location_id=(select ".$conf['location_id_field']." from ".$conf['location_table']." where ".$conf['location_title_field']."=city_title and substring(loc_kladr_code, 1, 2) =city_code limit 1)");
        foreach ($replacement as $k => $v)
        {
            mtoDb :: execute("update " . $conf['raw_table'] . ' set location_id=' . intval($v) . " where city_title='".$k."' and location_id is null");
        }
        $row = mtoDb :: fetchOneRow("select count(*) as c from " . $conf['raw_table'] . " where location_id is null");
        $this->out($row['c'] . " records skipped");
        mtoDb :: execute("delete from ".$conf['raw_table']." where location_id=0 or location_id is null");
        
        $this->out("raw data loaded");
        
        $list = mtoDb :: fetch("select * from " . $conf['raw_table']);
        foreach ($list as $line)
        {
            $target[] = '"' . $line['segm_start'] . '","' . $line['segm_end'] . '","'.ip2long($line['segm_start']).'","'.ip2long($line['segm_end']).'","' . (!empty($line['location_id']) ? $line['location_id'] : '0') . '","' . $line['city_title'] . '"';
        }
        
        mtoFs :: safeWrite("var/geo/geo.csv", implode("\n", $target));
        exec("perl tools/mtokit/geo/lib/geo2nginx.pl < var/geo/geo.csv > var/geo/geo.conf 2>&1", $res);
        $this->out("nginx converter:");
        $this->out($res);
        if (file_exists(self :: NGINX_PATH . "/geo.conf.old"))
        {
            mtoFs :: rm(self :: NGINX_PATH . "/geo.conf.old");
        }
        if (file_exists(self :: NGINX_PATH . "/geo.conf"))
        {
            mtoFs :: mv(self :: NGINX_PATH . "/geo.conf", self :: NGINX_PATH . "/geo.conf.old");
        }
        mtoFs :: cp("var/geo/geo.conf", self :: NGINX_PATH . "/geo.conf");
        
        mtoFs :: rm("var/geo");
        $this->out("Geobase updated");
        $this->out("You should restart nginx to take an effect");

        
        

    }
}
