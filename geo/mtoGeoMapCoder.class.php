<?php
mtoClass::import('mtokit/geo/mtoGeoMapWorkerGoogle.class.php');
mtoClass::import('mtokit/geo/mtoGeoMapWorkerYandex.class.php');

class mtoGeoMapCoder
{

    protected $worker;
    const  GEO_MAP_GOOGLE = 1;
    const  GEO_MAP_YANDEX = 2;

    

    function __construct($type=self::GEO_MAP_GOOGLE)
    {
        switch($type)
        {
            case self::GEO_MAP_GOOGLE:
                $this->worker = new mtoGeoMapWorkerGoogle();
            break;

            case self::GEO_MAP_YANDEX:
                $this->worker = new mtoGeoMapWorkerYandex();
            break;

        }
    }

    function getCoordsByAddress($address)
    {
        return $this->worker->getCoordsByAddress($address);
         

    }
    
    function getStatusCode()
    {
        return $this->worker->getStatusCode();
        
    }

    function getLimitExceeded()
    {
        return $this->worker->getLimitExceeded();
    }


}