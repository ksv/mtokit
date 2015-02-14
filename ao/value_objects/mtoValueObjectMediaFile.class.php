<?php
mtoClass :: import("mtokit/ao/value_objects/mtoValueObjectBase.class.php");

class mtoValueObjectMediaFile extends mtoValueObjectBase
{

    function get($args = array())
    {
        $filename = mtoToolkit :: instance()->getFilename("media_image_common", $this->_owner->getFilename(), $this->_owner->getId());
        $args = array(
            'w' => $args['w'],
            'h' => $args['h'],
            'changed' => $this->_owner->getLastChanged(),
            'skey' => $this->_owner->getUserId(),
            'id' => $this->_owner->getId()
        );
        return mtoToolkit :: instance()->getCache("media")->get($filename, $args);
    }

    function cloneMe(mtoActiveObject $clon)
    {
        $source = mtoToolkit :: instance()->getFilename("media_image_common", $this->_owner->getFilename(), $this->_owner);
        $new_filename = uniqid("media" . $clon->getId()) . "." . mtoToolkit :: instance()->getExtension($source);
        $clon->set("media_filename", $new_filename);
        $target = mtoToolkit :: instance()->getFilename("media_image_common", $new_filename, $clon);
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