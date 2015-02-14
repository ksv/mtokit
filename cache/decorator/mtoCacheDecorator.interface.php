<?php

mtoClass :: import('mtokit/cache/connection/mtoCacheConnection.interface.php');

interface mtoCacheDecorator extends mtoCacheConnection
{

    function getCache();
}