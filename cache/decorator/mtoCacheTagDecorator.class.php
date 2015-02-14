<?php

mtoClass :: import('mtokit/cache/decorator/mtoCacheBaseDecorator.class.php');

class mtoCacheTagDecorator extends mtoCacheBaseDecorator
{

    public $tags_prefix = 'mtotag_';

    function __construct($connection)
    {
        parent::__construct($connection);
    }

    protected function _resolveTagsKeys($tags_keys)
    {
        $suffix = mtoConf :: instance()->get("core", "suffix");
        if (is_array($tags_keys))
        {
            $new_keys = array();
            foreach ($tags_keys as $pos => $key)
            {
                $new_keys[] = $this->tags_prefix . $key;
            }
        }
        else
        {
            $new_keys = $this->tags_prefix . $tags_keys;
        }

        return $new_keys;
    }

    protected function _createTagsContainer($value, $tags)
    {
        $tags = $this->_resolveTagsKeys($tags);
        $tags_values = (array) $this->cache->get($tags);

        foreach ($tags as $tag_key)
        {
            if (!isset($tags_values[$tag_key]) || is_null($tags_values[$tag_key]))
            {
                $tags_values[$tag_key] = 0;
                $this->cache->add($tag_key, 0);
            }
        }

        return array('tags' => $tags_values, 'value' => $value);
    }

    protected function _isTagsValid($tags)
    {
        if (!is_array($tags))
        {
            if (isset($_SERVER['REQUEST_URI']))
            {
                $msg = $_SERVER['REQUEST_URI'] . " :: ";
                $msg .= lmbToolkit :: instance()->getCleanTrace(debug_backtrace());
                mtoProfiler :: instance()->logDebug($msg, "cache_error");
            }
        }
        $tags_versions = (array) $this->cache->get(array_keys($tags));

        foreach ($tags_versions as $tag_key => $tag_version)
        {
            if (is_null($tag_version) || $tags[$tag_key] != $tag_version)
            {
                return false;
            }
        }

        return true;
    }

    protected function _getFromTagsContainer($key, $container)
    {
        if ($this->_isTagsValid($container['tags']))
        {
            return $container['value'];
        }
        else
        {
            $this->cache->delete($key);
            return NULL;
        }
    }

    protected function _prepareValue($value, $tags_keys)
    {
        if (empty($tags_keys))
        {
            return $value;
        }
        if (!is_array($tags_keys))
        {
            $tags_keys = array($tags_keys);
        }

        return $this->_createTagsContainer($value, $tags_keys);
    }

    function add($key, $value, $ttl = false, $args = array())
    {
        return $this->cache->add($key, $this->_prepareValue($value, $args), $ttl);
    }

    function replace($key, $value, $ttl = false, $args = array())
    {
        return $this->cache->replace($key, $this->_prepareValue($value, $args), $ttl);
    }

    function set($key, $value, $ttl = false, $args = array())
    {
        return $this->cache->set($key, $this->_prepareValue($value, $args), $ttl);
    }

    function increment($key, $value=1)
    {
        return $this->cache->increment($key, $value);
    }

    function get($keys, $args = array())
    {
        if (isset($args['raw']) && $args['raw'])
        {
            return $this->cache->get($keys);
        }
        if (!$containers = $this->cache->get($keys))
        {
            return NULL;
        }


        if (!is_array($keys))
        {
            return $this->_getFromTagsContainer($keys, $containers);
        }

        $result = array();
        foreach ($containers as $key => $container)
        {
            if ($container)
            {
                $result[$key] = $this->_getFromTagsContainer($key, $container);
            }
            else
            {
                $result[$key] = NULL;
            }
        }

        return $result;
    }

    function delete($key)
    {
        $this->cache->delete($key);
    }

    function deleteByTag($tag)
    {
        $tag = $this->_resolveTagsKeys($tag);
        $this->cache->safeIncrement($tag);
    }

    function flush()
    {
        $this->cache->flush();
    }

    function dumpKeys($args = array())
    {
        return $this->cache->dumpKeys($args);
    }


}
