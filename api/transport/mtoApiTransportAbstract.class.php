<?php
abstract class mtoApiTransportAbstract
{
    
    abstract function decode($post = "");
    abstract function encode($data = array());
    
    
}