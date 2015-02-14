<?php
mtoClass :: import("mtokit/core/traits/mtoSingletone.trait.php");
class mtoHttpResponse
{
    use mtoSingletone;

    protected $__headers = array();


    function pushNoCache()
    {
	    $now = gmdate('D, d M Y H:i:s', strtotime("2000-01-01")) . ' GMT';
        $this->__headers[] = "Expires: " . $now;
        $this->__headers[] = "Last-Modified: ".$now;
        $this->__headers[] = "Cache-Control: no-cache, must-revalidate";
        $this->__headers[] = "Pragma: no-cache";

        foreach ($this->__headers as $h)
        {
            header($h);
        }
    }

    function redirect($url, $code = null)
    {
        if ($code)
        {
            header("Location: " . $url, $code);
        }
        else
        {
            header("Location: " . $url);
        }
    }

    function setCookie($name, $value, $expires=0, $args = array())
    {
//        if ($_SERVER['REMOTE_ADDR'] == DEBUG_IP_ADDRESS)
//        {
//            var_dump($name);
//            var_dump($value);
//            var_dump($expires ? time() + $expires * 24 * 3600 : 0);
//            var_dump(!empty($args['path']) ? $args['path'] : "/");
//            var_dump(!empty($args['domain']) ? $args['domain'] : mtoConf :: instance()->val("core|domain"));
//            die('dsds');
//        }

        setcookie(  $name,
                    $value,
                    $expires ? time() + $expires * 24 * 3600 : 0,
                    !empty($args['path']) ? $args['path'] : "/",
                    !empty($args['domain']) ? $args['domain'] : ("." . mtoConf :: instance()->val("core|domain"))
            );
        mtoToolkit :: instance()->getRequest()->setCookie($name, $value);
    }

}