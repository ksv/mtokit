<?php
abstract class mtoGeoMapWorkerAbstract
{
    protected $status_code;

    abstract function getCoordsByAddress($address);
    
    function getStatusCode()
    {
        return $this->status_code;
    }

}
