<?php

mtoClass :: import('mtokit/toolkit/mtoAbstractTools.class.php');

class mtoGeoTools extends mtoAbstractTools
{
    function ip2cidr($ip_start,$ip_end)
    {
        if (long2ip(ip2long($ip_start)) != $ip_start || long2ip(ip2long($ip_end)) != $ip_end)
        {
            return "0.0.0.0/0";
        }
        $ipl_start = (int) ip2long($ip_start);
        $ipl_end = (int) ip2long($ip_end);
        if ($ipl_start > 0 && $ipl_end < 0)
        {
            $delta = ($ipl_end + 4294967296) - $ipl_start;
        }
        else
        {
            $delta = $ipl_end - $ipl_start;
        }
        $netmask = str_pad(decbin($delta), 32, "0", STR_PAD_LEFT);
        if (ip2long($ip_start)==0 && substr_count($netmask,"1") == 32)
        {
            return "0.0.0.0/0";
        }
        if ($delta < 0 /*|| ($delta > 0 && $delta % 2 == 0)*/)
        {
            var_dump("ODD :: " . $delta);
            return "0.0.0.0/0";
        }
        for($mask=0; $mask<32; $mask++)
        {
            if ($netmask[$mask]==1)
            {
                break;
            }
        }
        if (preg_match("#^(0+)#", $netmask, $matches))
        {
            if (strlen($matches[1]) != $mask)
            {
                var_dump("EMPTY: " . $mask . " :: " . $netmask);
                return "0.0.0.0/0";
            }
        }
        return "$ip_start/$mask";
    }
    
    
}