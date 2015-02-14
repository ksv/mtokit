<?php
mtoClass::import('mtokit/geo/mtoGeoMapWorkerAbstract.class.php');


class mtoGeoMapWorkerGoogle extends mtoGeoMapWorkerAbstract
{

    /*
    200	G_GEO_SUCCESS	 No errors occurred; the address was successfully parsed and its geocode was returned.
    500	G_GEO_SERVER_ERROR	 A geocoding or directions request could not be successfully processed, yet the exact reason for the failure is unknown.
    601	G_GEO_MISSING_QUERY	 An empty address was specified in the HTTP q parameter.
    602	G_GEO_UNKNOWN_ADDRESS	 No corresponding geographic location could be found for the specified address, possibly because the address is relatively new, or because it may be incorrect.
    603	G_GEO_UNAVAILABLE_ADDRESS	 The geocode for the given address or the route for the given directions query cannot be returned due to legal or contractual reasons.
    610	G_GEO_BAD_KEY	 The given key is either invalid or does not match the domain for which it was given.
    620	G_GEO_TOO_MANY_QUERIES	 The given key has gone over the requests limit in the 24 hour period or has submitted too many requests in too short a period of time. If you're sending multiple requests in parallel or in a tight loop, use a timer or pause in your code to make sure you don't send the requests too quickly.
     *
     */


    function getCoordsByAddress($address)
    {
        $conf = mtoConf::instance()->loadConfig("config/mtokit_geo.ini")->getSection('geo_google');
        $base_url = "http://maps.google.com/maps/geo?output=xml" . "&key=" . $conf['key'];
        $request_url = $base_url . "&q=" . urlencode($address);

        $xml = simplexml_load_file($request_url);
        if (!$xml) return false;

        $status = $xml->Response->Status->code;

        $this->status_code = $status;

        if (strcmp($status, "200") == 0)
        {
              // Successful geocode
              $geocode_pending = false;
              $coordinates = $xml->Response->Placemark->Point->coordinates;
               // Format: Longitude, Latitude, Altitude
              return $coordinates;


         }

         return false;
    }


    function getLimitExceeded()
    {
        if ($this->getStatusCode()== '620')
        {
            return true;
        } else
        {
            return false;
        }
    }

    


}
