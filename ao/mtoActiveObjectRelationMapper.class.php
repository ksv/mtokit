<?php
class mtoActiveObjectRelationMapper
{
    protected $_owner = null;
    protected $_relations = array();
    protected $_db = null;
    protected $_toolkit = null;
    protected $_cache = null;
    protected $_data = array();
    protected $_m2m_data = array();
    protected $_ids_data = array();
    protected $_loaded = array();
    protected $_dirty = array();
    protected $_eager = false;
    protected $_m2m_index = -1;


    function __construct(mtoActiveObject $owner)
    {
        $this->_owner = $owner;
        $this->_toolkit = $this->_owner->getGuarded("toolkit");
        $this->_db = $this->_owner->getGuarded("db");
        $this->_cache = $this->_owner->getGuarded("cache");
    }

    function get($name, $default = null)
    {
        if ($this->isLoaded($name))
        {
            $this->log("RELLOADED: " . $name);
            return $this->_data[$name];
        }
        $this->log("RELGET: " . $name);
        $type = $this->getType($name);
        $rel = $this->getByTypeAndName($type, $name);
        $this->_init($rel);
        switch ($type)
        {
            case "o2o":
                $val = intval($this->_owner->getRaw($rel['rel_own_field']));
                if (!empty($val))
                {
                    $this->_data[$name] = mtoActiveObject :: create($rel['rel_model'], $val);
                }
            break;
            case "o2m":
                $ids = $this->getOneToManyIds($rel);
                $collection = $this->createCollection($rel, $ids);
                $this->_data[$name] = $collection;
            break;
            case "m2o":
                $val = intval($this->_owner->getRaw($rel['rel_own_field']));
                if ($val > 0)
                {
                    try
                    {
                        $this->_data[$name] = mtoActiveObject :: create($rel['rel_model'], $val);
                        $this->log("RELGETRES: " . $name . ":m2o:" . get_class($this->_data[$name]) . "#" . $val);
                    }
                    catch (ActiveObjectNotFoundException $e)
                    {
                        if (isset($rel['rel_required']) && $rel['rel_required'])
                        {
                            throw new mtoActiveObjectCoreException($e->getMessage());
                        }
                    }
                }
            break;
            case "m2m":
                $ids = $this->getManyToManyIds($rel);
                $collection = $this->createCollection($rel, $ids);

                $this->_data[$name] = $collection;
            break;
        }
        $this->_loaded[$name] = true;
        return $this->_data[$name];
    }

    protected function getOneToManyIds($rel)
    {
        if ($this->isLoaded($rel['rel_name']))
        {
            return $this->_ids_data[$rel['rel_name']];
        }
        if ($this->_owner->isNew())
        {
            return array();
        }
        $ids = $this->_cache->get($this->getKey($rel['rel_name']));
        if (!$ids)
        {
            $ids = array();
            $foreign_config = $this->_owner->getModelConfig($rel['rel_model']);
            $data = $this->_db->sql_getall("SELECT ".$foreign_config['pk']." from ".$foreign_config['table_name']." where ".$rel['rel_foreign_field']."=".$this->_owner->getId());


            foreach ($data as $row)
            {
                $ids[] = $row[$foreign_config['pk']];
            }
            $this->_cache->set($this->getKey($rel['rel_name']), $ids, false, array(CACHE_TAG_MODEL, CACHE_TAG_RELATION, $this->_owner->getGuarded("model_name")));
        }
        $this->_ids_data[$rel['rel_name']] = $ids;
        return $ids;
    }

    protected function getManyToManyIds($rel)
    {
        if ($this->isLoaded($rel['rel_name']))
        {
            return $this->_ids_data[$rel['rel_name']];
        }
        if ($this->_owner->isNew())
        {
            return array();
        }
        $ids = $this->_cache->get($this->getKey($rel['rel_name']));
        if (!$ids)
        {
            $ids = array();
            $data = $this->_db->sql_getall("SELECT ".$rel['rel_foreign_field']." from ".$rel['rel_table']." where ".$rel['rel_own_field']."=".$this->_owner->getId());
            foreach ($data as $row)
            {
                $ids[] = $row[$rel['rel_foreign_field']];
            }
            $this->_cache->set($this->getKey($rel['rel_name']), $ids, false, array(CACHE_TAG_MODEL, CACHE_TAG_RELATION, $this->_owner->getGuarded("model_name")));
        }
        $this->_ids_data[$rel['rel_name']] = $ids;
        return $ids;
    }

    function getIds($name)
    {
        $this->get($name);
        if (isset($this->_ids_data[$name]))
        {
            return $this->_ids_data[$name];
        }
        $type = $this->getType($name);
        $rel = $this->getByTypeAndName($type, $name);
        switch ($type)
        {
            case "o2m":
                return $this->getOneToManyIds($rel);
            break;
            case "m2m":
                return $this->getManyToManyIds($rel);
            break;
            case "m2o":
        	$obj = $this->get($name);
        	if ($obj instanceof mtoActiveObject)
    		{        	                
                    return $this->get($name)->getId();
                }
            break;
        }
    }


    function set($property, $value)
    {
        $this->log("RELSET: " . $property);
        $type = $this->getType($property);
        $rel = $this->getByTypeAndName($type, $property);
        $this->_init($rel);
        switch ($type)
        {
            case "o2o":
                if ($value instanceof mtoActiveObject)
                {
                    $this->_data[$property] = $value;
                }
                elseif (is_numeric($value))
                {
                    $this->_data[$property] = mtoActiveObject :: create($rel['rel_model'], $value);
                }
            break;
            case "o2m":
                if (!is_array($value))
                {
                    $value = array($value);
                }
                $collection = new mtoActiveObjectCollection();
                $ids = array();
                foreach ($value as $entry)
                {
                    if ($entry instanceof mtoActiveObject)
                    {
                        if (!$this->_owner->isNew())
                        {
                            $entry->set($rel['rel_foreign_field'], $this->_owner->getId());
                        }
                        $collection->add($entry);
                        $ids[] = $entry->getId();
                    }
                    elseif (is_numeric($entry) && intval($entry) > 0)
                    {
                        $object = mtoActiveObject :: create($rel['rel_model'], intval($entry));
                        if (!$this->_owner->isNew())
                        {
                            $object->set($rel['rel_foreign_field'], $this->_owner->getId());
                        }
                        $collection->add($object);
                        $ids[] = $entry;
                    }
                }
                $this->_data[$property] = $collection;
                $this->_ids_data[$property] = $ids;
            break;
            case "m2o":
                if ($value instanceof mtoActiveObject)
                {
                    $this->_owner->set($rel['rel_own_field'], $value->getId());
                    $this->_data[$property] = $value;
                }
                elseif (is_numeric($value) && intval($value) > 0)
                {
                    $object = mtoActiveObject :: create($rel['rel_model'], intval($value));
                    $this->_owner->set($rel['rel_own_field'], $value);
                    $this->_data[$property] = $object;
                }
                else
                {
                    $this->_owner->set($rel['rel_own_field'], 0);
                    $this->_data[$property] = null;
                }
            break;
            case "m2m":
                $this->_data[$property] = new mtoActiveObjectCollection();
                $this->_m2m_data[$property] = array();
                if (!is_array($value))
                {
                    $value = array($value);
                }
                foreach ($value as $entry)
                {
                    $object = $this->_createManyObject($entry, $rel);
                    if ($object instanceof mtoActiveObject)
                    {
                        $this->_addToMany($rel, $object);
                    }
                }
            break;
        }
        $this->_dirty[$property] = true;
        $this->_loaded[$property] = true;
    }


    function clean($property)
    {
        $this->log("RELCLEAN: " . $property);
        $this->_data[$property] = null;
        unset($this->_loaded[$property]);
    }

    function add($property, $value)
    {
        $this->log("RELADD: " . $property);
        $type = $this->getType($property);
        $rel = $this->getByTypeAndName($type, $property);
        if (!isset($this->_ids_data[$property]))
        {
            $this->_ids_data[$property] = array();
        }
        switch ($type)
        {
            case "o2m":
                if ($value instanceof mtoActiveObject)
                {
                    $this->log("RELADD:AO:" . get_class($value) . "#" . $value->getId());
                    $object = $value;
                    //if (!in_array(intval($value->getId()), $this->_ids_data[$property]))
                    //{
                        $this->get($property)->add($object);
                        $this->_ids_data[$property][] = intval($value->getId());
                    //}
                }
                elseif (is_numeric($value) && intval($value) > 0)
                {
                    //if (!in_array(intval($value), $this->_ids_data[$property]))
                    //{
                        $this->log("RELADD:INT:" . $value);
                        $object = mtoActiveObject :: create($rel['rel_model'], $value);
                        $this->get($property)->add($object);
                        $this->_ids_data[$property][] = intval($value);
                    //}
                }
                else
                {
                    return;
                }
                if (!$this->_owner->isNew())
                {
                    $object->set($rel['rel_foreign_field'], $this->_owner->getId());
                }
            break;
            case "m2m":
                $object = $this->_createManyObject($value, $rel);
                if ($object instanceof mtoActiveObject)
                {
                    $this->_addToMany($rel, $object);
                }
            break;
        }
        $this->_dirty[$property] = true;
        $this->_loaded[$property] = true;
    }


    function removeById($property, $id)
    {
        $type = $this->getType($property);
        $rel = $this->getByTypeAndName($type, $property);
        switch ($type)
        {
            case "o2m":
                //if (isset($rel['rel_cascade']) && $rel['rel_cascade'])
                //{
                //    mtoActiveObject :: create($rel['rel_model'], intval($id))->destroy();
                //}
                //else
                //{
                    $conf = $this->_owner->getModelConfig($rel['rel_model']);
                    $this->removeByField($property, $conf['pk'], $id);
                //}
            break;
            case "m2m":
                $this->_db->sql_query("delete from " . $rel['rel_table']." where " . $rel['rel_table_pk']." = " . intval($id));
                $this->_cache->delete($this->getMKey($property));
            break;
        }
        $this->_cache->delete($this->getKey($property));
        $this->_dirty[$property] = true;
    }

    function removeByField($property, $field, $value)
    {
        $type = $this->getType($property);
        $rel = $this->getByTypeAndName($type, $property);
        switch ($type)
        {
            case "o2m":
                $item = $this->get($property)->removeByField($field, $value);
                if ($item instanceof mtoActiveObject)
                {
                    if (isset($rel['rel_cascade']) && $rel['rel_cascade'])
                    {
                        $item->destroy();
                    }
                    else
                    {
                        $item->set($rel['rel_foreign_field'], 0);
                        $item->save();
                    }
                }
            break;
            case "m2m":
                $this->get($property)->removeByField($field, $value);
                if (!isset($this->_m2m_data[$property]))
                {
                    return;
                }
                foreach ($this->_m2m_data[$property] as $key => $entry)
                {
                    if (isset($entry[$field]) && $entry[$field] == $value)
                    {
                        unset($this->_m2m_data[$property][$key]);
                        break;
                    }
                }
            break;
        }
        $this->_dirty[$property] = true;
    }

    protected function createCollection($rel, $ids)
    {
        $dataset = array();
        foreach ($ids as $id)
        {
            $object = mtoActiveObject :: create($rel['rel_model'], intval($id));
            if ($rel['rel_type'] == "m2m")
            {
                if (isset($this->_m2m_data[$rel['rel_name']][$id]))
                {
                    
                    if (isset($rel['rel_extended_columns']) && is_array($rel['rel_extended_columns']))
                    {
                        foreach ($rel['rel_extended_columns'] as $k => $col)
                        {
                            if (isset($this->_m2m_data[$rel['rel_name']][$id][$k]))
                            {
                                $object->set("extended_" . $k, $this->_m2m_data[$rel['rel_name']][$id][$k]);
                            } else
                            {
                                $object->set("extended_" . $k, '');
                            }    
                        }
                    }
                    if (isset($rel['rel_table_pk']))
                    {
                        $object->set("extended_" . $rel['rel_table_pk'], $this->_m2m_data[$rel['rel_name']][$id][$rel['rel_table_pk']]);
                    }
                    $object->set("extended_" . $rel['rel_foreign_field'], $object->getId());
                }
            }
            $dataset[] = $object;
        }
        return new mtoActiveObjectCollection($dataset);
    }


    function preSave()
    {
        $this->log("RELPRESAVE");
        foreach ($this->_owner->getColumnsConfig() as $index => $column)
        {
            $is_relation = false;
            switch ($column['type'])
            {
                case "m2o":
                    $item = $this->get($index);
                    if ($item instanceof mtoActiveObject)
                    {
                        if ($item->isDirty())
                        {
                            $item->save();
                        }
                        $config = $item->getColumnsConfig();
                        foreach ($config as $k => $c)
                        {
                            if ($c['type'] == "o2m" && $c['rel_model'] == mto_under_scores(get_class($this->_owner)))
                            {
                                $this->_cache->delete($item->getRelKey($k));
                                $this->log("DELC: " . $item->getRelKey($k));
                            }
                        }
                    }
                    if ($this->_owner->getId())
                    {
                        $is_relation = true;
                    }
                break;
            }
            if ($is_relation)
            {
                $this->_cache->delete($this->getKey($index));
                $this->log("DELC: " . $this->getKey($index));
            }
        }
    }

    function postSave()
    {
        $this->log("RELPOSTSAVE");
        foreach ($this->_owner->getColumnsConfig() as $index => $column)
        {
            if (!$this->isDirty($index))
            {
                $this->log("IAM CLEAN: " . $index);
                continue;
            }
            $is_relation = false;
            switch ($column['type'])
            {
                case "o2m":
                    $collection = $this->get($index);
                    foreach ($collection as $item)
                    {
                        if ($item->isDirty() || $this->_owner->getGuarded("is_beeing_creating"))
                        {
                            if ($this->_owner->getGuarded("is_beeing_creating"))
                            {
                                $item->set($column['rel_foreign_field'], $this->_owner->getId());
                            }
                            $item->save();
                        }
                    }
                    $is_relation = true;
                break;
                case "o2o":
                    $item = $this->get($index);
                    if ($item instanceof mtoActiveObject && $item->isDirty())
                    {
                        $item->save();
                    }
                    $is_relation = true;
                break;
                case "m2m":
                    
                    $collection = $this->get($index);                    
                    
                    $rel_table_pk = isset($column['rel_table_pk']) ? $column['rel_table_pk'] : "id";
                    $exists = $this->_db->sql_keygetall($column['rel_foreign_field'], "select * from " . $column['rel_table']." where " . $column['rel_own_field']."=".$this->_owner->getId());
                                        
                    $i = 0;
                    foreach ($collection as $item)
                    {
                        
                        if ($item->isDirty() && !$item->isNew())
                        {
                            $item->save();
                            $item_index = $item->getId();
                        }
                        elseif ($item->isNew())
                        {
                            $item->save();
                            $item_index = $item->get("extended_m2m_index");
                        }
                        else
                        {
                            $item_index = $item->getId();
                        }
                        
                        
                        
                        
                        if (isset($this->_m2m_data[$index][$item_index]))
                        {
                            $this->_m2m_data[$index][$item_index][$column['rel_own_field']] = $this->_owner->getId();
                            $this->_m2m_data[$index][$item_index][$column['rel_foreign_field']] = $item->getId();
                            if ($item_index < 0)
                            {
                                $this->_m2m_data[$index][$item->getId()] = $this->_m2m_data[$index][$item_index];
                                unset($this->_m2m_data[$index][$item_index]);
                            }
                            $link = $this->_m2m_data[$index][$item->getId()];
                            if (!isset($link[$rel_table_pk]))
                            {
                                $link[$rel_table_pk] = 0;
                            }
                            if ($link[$rel_table_pk] > 0)
                            {
                                $this->_db->sql_update($column['rel_table'], $link, $rel_table_pk);
                            }
                            else
                            {
                                if (array_key_exists($item->getId(), $exists))
                                {
                                    $link[$rel_table_pk] = $exists[$item->getId()][$rel_table_pk];
                                    $this->_db->sql_update($column['rel_table'], $link, $rel_table_pk);
                                }
                                else
                                {
                                    $sql = $this->_db->sql_getinsertsql($column['rel_table'], $link);
                                    $this->_db->sql_query($sql);
                                }
                            }
                        }
                    }

                                        
                    foreach ($exists as $ekey => $eentry)
                    {
                        
                        if (!array_key_exists($ekey, $this->_m2m_data[$index]))
                        {
                            $this->_db->sql_query("delete from " . $column['rel_table']." where " . $rel_table_pk . " = " . $eentry[$rel_table_pk]);
                        }
                    }

                    $is_relation = true;
                    $this->_cache->delete($this->getMKey($index));
                    $this->log("DELC: " . $this->getMKey($index));
                break;
            }
            if ($is_relation)
            {
                $this->_cache->delete($this->getKey($index));
                $this->log("DELC: " . $this->getKey($index));
            }
        }
    }


    function cloneAll(mtoActiveObject $target, $m2m = true)
    {
        foreach ($this->_owner->getColumnsConfig() as $index => $column)
        {
            switch ($column['type'])
            {
                case "m2o":
                break;
                case "o2o":
                    $item = $this->get($index);
                    $clon = $item->cloneMe();
                    $target->set($index, $clon);
                break;
                case "o2m":
                    foreach ($this->get($index) as $item)
                    {
                        $clon = $item->cloneMe(null,false, array($column['rel_foreign_field'] => $target->getId()));
                        $target->addToRelation($index, $clon);
                    }
                    $target->cleanRelation($index);
                break;
                case "m2m":
                    if (!$m2m)
                    {
                        continue;
                    }
                    $list = array();
                    foreach ($this->get($index) as $item)
                    {
                        $list[] = $item;
//                        var_dump($index);
//                        var_dump(get_class($item));
//                        var_dump($item->getId());
//                        $target->addToRelation($index, $item);
                    }
                    $target->set($index, $list);
                break;
            }
        }
    }


    function destroy()
    {
        $this->log("RELDESTROY");
        foreach ($this->_owner->getColumnsConfig() as $index => $column)
        {
            $is_relation = false;
            switch ($column['type'])
            {
                case "m2m":
                    $this->_db->sql_query("DELETE from ".$column['rel_table']." where ".$column['rel_own_field']." =".$this->_owner->getId());
                    $is_relation = true;
                    $this->_cache->delete($this->getMKey($index));
                break;
                case "o2o":
                    if (isset($column['rel_cascade']) && $column['rel_cascade'])
                    {
                        $ref = $this->get($index);
                        $ref->destroy();
                    }
                    $is_relation = true;
                break;
                case "o2m":
                    $collection = $this->get($index);
                    if (isset($column['rel_cascade']) && $column['rel_cascade'])
                    {
                        foreach ($collection as $item)
                        {
                            $item->destroy();
                        }
                    }
                    else
                    {
                        foreach ($collection as $item)
                        {
                            $item->set($column['rel_foreign_field'], 0);
                            $item->save();
                        }
                    }
                    $is_relation = true;
                break;
                case "m2o":
                    $this->destroyParent($this->get($index), $column);
                    $this->clean($index);
                break;
            }
            if ($is_relation)
            {
                $this->_cache->delete($this->getKey($index));
            }
        }
    }

    protected function destroyParent($obj, $conf)
    {
        $this->log("RELDESTROYPARENT: " . get_class($obj));
        if ($obj != null && $obj instanceof mtoActiveObject)
        {
            $parentConf = $obj->getConfig();
            foreach ($parentConf['columns'] as $index => $column)
            {
                if ($column['type'] == "o2m" && $column['rel_model'] == mto_under_scores(get_class($this->_owner)))
                {
                    if (isset($conf['rel_index']) && !empty($conf['rel_index']) && isset($column['rel_index']) && !empty($column['rel_index']))
                    {
                        if ($conf['rel_index'] == $column['rel_index'])
                        {
                            $obj->cleanRelation($index);
                            $this->_cache->delete($obj->getRelKey($index));
                        }
                    }
                    else
                    {
                        $this->_cache->delete($obj->getRelKey($index));
                    }
                }
            }
        }
    }

    function define()
    {
        $this->_relations = array(
            'o2m' => array(),
            'm2o' => array(),
            'm2m' => array(),
            'o2o' => array()
        );
        foreach ($this->_owner->getColumnsConfig() as $column => $conf)
        {
            if (isset($conf['type']) && in_array($conf['type'], array_keys($this->_relations)))
            {
                $this->_relations[$conf['type']][$column] = array(
                    'rel_type' => $conf['type'],
                    'rel_name' => $column,
                    'rel_model' => $conf['rel_model'],
                    'rel_table' => isset($conf['rel_table']) ? $conf['rel_table'] : null,
                    'rel_own_field' => isset($conf['rel_own_field']) ? $conf['rel_own_field'] : null,
                    'rel_foreign_field' => isset($conf['rel_foreign_field']) ? $conf['rel_foreign_field'] : null,
                    'rel_cascade' => isset($conf['rel_cascade']) ? $conf['rel_cascade'] : false,
                    'rel_table_pk' => isset($conf['rel_table_pk']) ? $conf['rel_table_pk'] : null,
                    'rel_extended_columns' => isset($conf['extended_columns']) ? $conf['extended_columns'] : null
                );
            }
        }
    }

    function has($name)
    {
        foreach ($this->_relations as $type)
        {
            if (isset($type[$name]))
            {
                return true;
            }
        }
        return false;
    }

    function getType($name)
    {
        foreach ($this->_relations as $key => $rels)
        {
            foreach ($rels as $k => $v)
            {
                if ($name == $k)
                {
                    return $key;
                }
            }
        }
        _D(debug_backtrace(), true);
        throw new mtoActiveObjectCoreException("Unknown relation " . $name . " in AO: " . $this->_owner->getClass());
    }

    function getByTypeAndName($type, $name)
    {
        if (isset($this->_relations[$type][$name]))
        {
            return $this->_relations[$type][$name];
        }
        else
        {
            throw new mtoActiveObjectCoreException("Unknown relation " . $name . " in AO: " . $this->_owner->getClass());
        }
    }


    function _get($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    function _set($name, $value)
    {
        $this->_data[$name] = $value;
    }

    function _has($name)
    {
        return isset($this->_data[$name]);
    }


    function getKey($name)
    {
        return "rel_".get_class($this->_owner)."_".$this->_owner->getId()."_".$name;
    }

    function isLoaded($name)
    {
        return array_key_exists($name, $this->_loaded);
    }

    function isDirty($name)
    {
        return array_key_exists($name, $this->_dirty);
    }

    function getMKey($name)
    {
        return "m2m_".get_class($this->_owner)."_".$this->_owner->getId()."_".$name;
    }

    protected function _loadMany($rel)
    {
        $this->_data[$rel['rel_name']] = new mtoActiveObjectCollection();
        if ($this->_owner->isNew())
        {
            $this->_m2m_data[$rel['rel_name']] = array();
            return;
        }
        $this->_m2m_data[$rel['rel_name']] = $this->_cache->get($this->getMKey($rel['rel_name']));
        if (!$this->_m2m_data[$rel['rel_name']])
        {
            $this->_m2m_data[$rel['rel_name']] = $this->_db->sql_keygetall($rel["rel_foreign_field"], "select * from " . $rel['rel_table']." where " . $rel['rel_own_field'] . "=" . intval($this->_owner->getId()));
            $this->_cache->set($this->getMKey($rel['rel_name']), $this->_m2m_data[$rel['rel_name']], false, array(CACHE_TAG_MODEL, CACHE_TAG_RELATION, $this->_owner->getGuarded("model_name")));
        }
    }

    protected function _addToMany($rel, mtoActiveObject $object)
    {
        $property = $rel['rel_name'];
        $this->get($property)->add($object);
        if ($object->isNew())
        {
            $index = $this->_m2m_index;
            $object->set("extended_m2m_index", $index);
            $this->_m2m_index--;
        }
        else
        {
            $index = $object->getId();
        }

        $this->_ids_data[$property] = $object->getId();
        $this->_m2m_data[$property][$index] = array();
        if (isset($rel['rel_extended_columns']) && is_array($rel['rel_extended_columns']))
        {
            foreach ($rel['rel_extended_columns'] as $k => $col)
            {
                if ($object->has("extended_" . $k))
                {
                    $this->_m2m_data[$property][$index][$k] = $object->get("extended_" . $k);
                }
                else
                {
                    $this->_m2m_data[$property][$index][$k] = null;
                }
            }
        }
        $rel_table_pk = isset($rel['rel_table_pk']) ? $rel['rel_table_pk'] : "id";
        $this->_m2m_data[$property][$index][$rel_table_pk] = 0;
        if (!$this->_owner->isNew())
        {
            $this->_m2m_data[$property][$index][$rel['rel_own_field']] = $this->_owner->getId();
        }
        if (!$object->isNew())
        {
            $this->_m2m_data[$property][$index][$rel['rel_foreign_field']] = $object->getId();
        }
    }

    protected function _createManyObject($value, $rel)
    {
        if ($value instanceof mtoActiveObject)
        {
            return $value;
        }
        elseif (is_numeric($value) && intval($value) > 0)
        {
            return mtoActiveObject :: create($rel['rel_model'], intval($value));
        }
        elseif (is_array($value) && isset($value[$rel['rel_foreign_field']]))
        {
            $object = mtoActiveObject :: create($rel['rel_model'], intval($value[$rel['rel_foreign_field']]));
            if (isset($rel['rel_extended_columns']) && is_array($rel['rel_extended_columns']))
            {
                foreach ($rel['rel_extended_columns'] as $k => $col)
                {
                    if (isset($value[$k]))
                    {
                        $object->set("extended_".$k, $value[$k]);
                    }
                }
            }
            $object->set("extended_" . $rel['rel_foreign_field'], $object->getId());
            return $object;
        }
        else
        {
            return null;
        }
    }

    protected function _init($rel)
    {
        $property = $rel['rel_name'];
        if (isset($this->_data[$property]))
        {
            return;
        }
        switch ($rel['rel_type'])
        {
            case "o2o":
                $this->_data[$property] = null;
            break;
            case "m2o":
                $this->_data[$property] = null;
            break;
            case "o2m":
                $this->_data[$property] = new mtoActiveObjectCollection();
                $this->_ids_data[$property] = array();
            break;
            case "m2m":
                $this->_data[$property] = new mtoActiveObjectCollection();
                $this->_ids_data[$property] = array();
                $this->_m2m_data[$property] = array();
                $this->_loadMany($rel);
            break;
            default:
                $this->_data[$property] = null;
            break;
        }
    }

    function dumpMany()
    {
        print_r($this->_m2m_data);
    }

    function log($msg)
    {
        $this->_owner->log($msg);
    }
    

}