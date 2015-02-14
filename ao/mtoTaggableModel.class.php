<?php

mtoClass :: import('mtokit/ao/mtoThumbnailModel.class.php');
abstract class mtoTaggableModel extends mtoThumbnailModel
{
    function setTags($tags)
    {
        
        $rel = $this->_relmapper->getByTypeAndName("m2m", "tags");
        //$rel = $this->getRelationByTypeAndName();

        $class = mto_camel_case($rel['rel_model']);
        $list = array();
        if (is_array($tags))
        {
            foreach ($tags as $tag)
            {
                $tagObj = new $class();
                $one_tag = $tagObj->getOrCreateByName(trim($tag));
                $list[] = $one_tag;
                //var_dump($one_tag->getId());
            }
        }

        //$this->setRelation("tags", $list);
        $this->_relmapper->set('tags', $list);

        foreach ($list as $item)
        {
            if (is_object($item))
            {
                $this->updateTagCounter($item);
            }
        }

        $this->updateTagsTotal();
    }

    function setTagsString($str)
    {
        $tags = explode(',',$str);
        $this->setTags($tags);
    }


    function getTags()
    {
        $ret_tags = array();
        $list = $this->_relmapper->get("tags");
        foreach ($list as $item)
        {
            $ret_tags[] = $item->getTitle();
        }

        return $ret_tags;
    }

    function getTagsString()
    {
        return implode(', ', $this->getTags());
    }

     function getTagsAsString()
    {
        return $this->getTagsString();
    }

    function updateTagsTotal()
    {

    }


}
