<?php
/**
* Import file via http
*/
class mtoUploadedFileUrl
{
    protected $download_url = null;

    function __construct($download_url) 
    {
        $this->download_url = filter_var($download_url, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Fetch and save file
     * @param string $path Path to save file
     * @return bool
     * @access public
     */
    public function save($path)
    {
        $curl_options = array (
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_BINARYTRANSFER => 1,
        );

        $file = $this->curl_query($curl_options);

        if (!$file) 
            return false;

        if (($handle = fopen($path, 'w')) === false)
            return false;

        if ((fwrite($handle, $file)) === false)
            return false;

        fclose($handle);
        return true;
    }

    /**
     * Returns filename from download url
     * @return mixed
     * @access public
     */
    public function getName()
    {
        if (is_null($this->download_url))
            return false;

        $uri = explode('/', $this->download_url);
        return array_pop($uri);
    }

    /**
     * Returns 'Content-Length' from HTTP headers as importing file size, without downloading it.
     * @return mixed
     * @access public
     */
    public function getSize()
    {
        $curl_options = array (
            CURLOPT_HEADER => 1,
            CURLOPT_NOBODY => 1,
            CURLOPT_RETURNTRANSFER => 1,
        );

        $headers = $this->curl_query($curl_options);

        if (!$headers) return false;

        /* grep Content-Length from response */
        preg_match('/(Content-Length: \d{1,})/', $headers, $content_length);
        preg_match('/\d{1,}/', $content_length[0], $size);

        return intval($size[0]);
    }

    /**
     * @param array $query_args curl options
     * @return mixed
     * @access private
     */
    private function curl_query($query_args)
    {
        if (is_null($this->download_url)) 
            return false;

        $curl_options = array(
            CURLOPT_URL => $this->download_url,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 3
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);
        curl_setopt_array($ch, $query_args);

        $raw = curl_exec($ch);
        $log = curl_getinfo($ch);

        curl_close($ch);

        return ($log['http_code'] == 200) ? $raw : false;
    }
}