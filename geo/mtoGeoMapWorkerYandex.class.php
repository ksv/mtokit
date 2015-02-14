<?php
mtoClass::import('mtokit/geo/mtoGeoMapWorkerAbstract.class.php');

 class mtoGeoMapWorkerYandex extends mtoGeoMapWorkerAbstract
{
    function getCoordsByAddress($address)
    {
        $conf = mtoConf::instance()->loadConfig("config/mtokit_geo.ini")->getSection('geo_yandex');
        $base_url = "http://geocode-maps.yandex.ru/1.x/?results=1" . "&key=" . $conf['key'];
        $request_url = $base_url . "&geocode=" . urlencode($address);

        $xml = simplexml_load_file($request_url);
        if (!$xml)
        {
            var_dump($xml);
            return false;
        }

        $found =  $xml->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found;
        if ($found>0)
        {
            $pos = $xml->GeoObjectCollection->featureMember->GeoObject->Point->pos;
            $coordinates = str_replace(' ',',',$pos);

            return $coordinates;
        }

         return false;

    }

    function getStatusCode()
    {
        
    }

    function getLimitExceeded()
    {
      return false;
    }


}
