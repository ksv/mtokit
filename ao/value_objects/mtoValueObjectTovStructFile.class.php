<?php
mtoClass :: import("mtokit/ao/value_objects/mtoValueObjectBase.class.php");

class mtoValueObjectTovStructFile extends mtoValueObjectBase
{

    function get($args = array())
    {
//        $filename = mtoToolkit :: instance()->getFilename("product", $this->_owner->getImage(), $this->_owner->getId());
//        $args = array(
//            'w' => $args['w'],
//            'h' => $args['h'],
//            'changed' => $this->_owner->getLastChanged(),
//            'skey' => $this->_owner->getUserId(),
//            'id' => $this->_owner->getId()
//        );
//        return mtoToolkit :: instance()->getCache("media")->get($filename, $args);
    }

    function cloneMe(mtoActiveObject $clon)
    {
        $source = mtoToolkit :: instance()->getFilename("product", $this->_owner->getImage(), $this->_owner->getTovarId(), array('user_id' => $this->_owner->getTovar()->getOwner()));
        $new_filename = uniqid("front" . $clon->getId()) . "." . mtoToolkit :: instance()->getExtension($source);
        $clon->set("image", $new_filename);
        $target = mtoToolkit :: instance()->getFilename("product", $new_filename, $clon->getTovarId(), array('user_id' => $clon->getTovar()->getOwner()));
        mtoFs :: mkdir(dirname($target));
        copy($source, $target);
    }

    function set($value)
    {

    }

    protected function parseArgs($name)
    {
        $parts = explode("_", $name);
        list($w, $h) = explode("x", array_shift($parts));
        return array(
            'w' => $w, 'h' => $h
        );
    }


}