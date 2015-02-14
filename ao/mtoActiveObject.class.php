<?php
mtoClass::import("mtokit/ao/mtoActiveObjectCollection.class.php");
mtoClass::import("mtokit/ao/mtoActiveObjectContainer.class.php");
mtoClass::import("mtokit/ao/mtoActiveObjectException.class.php");
mtoClass::import("mtokit/ao/mtoActiveObjectRelationMapper.class.php");
mtoClass::import("mtokit/ao/mtoActiveObjectValueObjectMapper.class.php");
mtoClass::import("mtokit/toolkit/mtoToolkit.class.php");
mtoClass::import("mtokit/ao/mtoActiveObjectNotFoundException.class.php");
mtoClass::import("mtokit/ao/mtoActiveObjectCoreException.class.php");



abstract class mtoActiveObject implements ArrayAccess
{
    protected $_id=0;
    protected $_table_name;
    protected $_prefix = null;
    protected $_pm_key = 'id';
    protected $_db = null;
    protected $_toolkit;
    protected $_model_name;
    protected $_cache = null;
    protected $_config = array();
    protected $_table_info = array();
    protected $_eager = false;
    protected $_dirty = false;
    protected $_is_beeing_creating = false;
    protected $_is_beeing_saving = false;
    protected $_dirty_props = array();
    protected $_relmapper = null;
    protected $_vomapper = null;




    //=============== creation
    //=======================
    //=======================
    function __construct($id = 0, $eager = false)
    {
        $this->_model_name = strtolower(mto_under_scores(get_class($this)));
        $this->_id = $id;
        $this->_eager = $eager;

        if (!isset($GLOBALS['model_config'][$this->_model_name]))
        {
            throw new mtoActiveObjectCoreException("Model config not found: " . $this->_model_name);
        }
        $config = $GLOBALS['model_config'][$this->_model_name];
        if (isset($config['prefix']))
        {
            $this->_prefix = $config['prefix'];
        }
        $this->_table_name = $config['table_name'];
        $this->_config = $config;
        $this->_toolkit = mtoToolkit :: instance();
        $this->_db = $this->_toolkit->getDbConnection();
        $this->_cache = $this->_toolkit->getCache("redis");
        $this->_relmapper = new mtoActiveObjectRelationMapper($this);
        $this->_relmapper->define();
        $this->_vomapper = new mtoActiveObjectValueObjectMapper($this);
        $this->_vomapper->define();
        if (!empty($this->_prefix))
        {
            $this->_pm_key = $this->_prefix.'_id';
        }
        $this->log("CNST: " . get_class($this) . "#" . $id);

        $this->_getTableInfo();
        $this->_load($id);
    }

    static function create($model = null, $id = null)
    {
//        if (empty($model))
//        {
//            _D(debug_backtrace(), true);
//            var_dump($trace);
//        }
        if (is_null($model))
        {
            $model = get_called_class();
        }
        $class = mto_camel_case($model);
        mtoClass::import("classes/model/" . $class.".class.php");
        return new $class($id);
    }


    //=================property handling
    //=======================
    //=======================
    function getId()
    {
        return $this->_id;
    }

    function hasRaw($name)
    {
        if (!empty($this->_prefix))
        {
            return  property_exists($this, $name) || property_exists($this, $this->_prefix . "_" . $name);
        }
        return property_exists($this, $name);
    }

    function getRaw($name)
    {
        if($this->hasRaw($name))
        {
            if (!empty($this->_prefix))
            {
                if (property_exists($this, $this->_prefix."_".$name))
                {
                    $name = $this->_prefix."_".$name;
                    return $this->$name;
                }
            }
            return $this->$name;
        }
    }

    function setRaw($name, $value)
    {
        if (empty($name))
        {
            echo 'empty property name '.$name;
            //_D(debug_backtrace(), true);
        }
        $this->$name = $value;
    }

    function importRaw($values)
    {
        if(!is_array($values))
        {
            return;
        }

        foreach($values as $property => $value)
        {
            if(!$this->isGuarded($property))
            {
                $this->setRaw($property, $value);
            }
        }
        if (isset($values[$this->_config['pk']]))
        {
            $this->_id = $values[$this->_config['pk']];
        }
    }

    function exportRaw()
    {
        $exported = array();
        foreach(get_object_vars($this) as $name => $var)
        {
            if(!$this->isGuarded($name))
            {
                $exported[$name] = $this->getRaw($name);
            }
        }
        return $exported;
    }

    function has($name)
    {
        if (strpos($name, "v_obj_") === 0)
        {
            $name = str_replace("v_obj_", "", $name);
        }
        return $this->hasRaw($name) || $this->_mapPropertyToMethod($name) || $this->_relmapper->has($name);
    }

    function get($name, $default = null)
    {
        if (is_null($name) || empty($name))
        {
            throw new mtoActiveObjectCoreException("Attempt to get empty property in AO: " . $this);
        }
        if($this->hasRaw($name) && !$this->isGuarded($name))
        {
            return $this->getRaw($name);
        }
        if($method = $this->_mapPropertyToMethod($name))
        {
            return $this->$method();
        }

        if ($this->_relmapper->has($name))
        {
            return $this->_relmapper->get($name);
        }
        if (strpos($name, "v_obj_") === 0)
        {
            $name = str_replace("v_obj_", "", $name);
            if (!empty($this->_prefix))
            {
                if ($this->_vomapper->has($this->_prefix . "_" . $name))
                {
                    return $this->_vomapper->getObject($this->_prefix . "_" . $name);
                }
            }
            if ($this->_vomapper->has($name))
            {
                return $this->_vomapper->getObject($name);
            }
        }

        if(null !== $default)
        {
            return $default;
        }

        _D(debug_backtrace(), true);
        throw new pdException("No such property '$name' in " . get_class($this));
    }

    function set($property, $value)
    {
        if (empty($property))
        {
            _D(debug_backtrace(), true);
            throw new mtoActiveObjectCoreException("Empty property requested for " . get_class($this));
        }
        if($this->hasRaw($property) && !$this->isGuarded($property))
        {
            $this->_dirty = true;
            $this->setRaw($property, $value);
            if (!empty($this->_prefix))
            {
                if (strpos($property, $this->_prefix."_") !== 0)
                {
                    $property = $this->_prefix . "_" . $property;
                }
            }
            $this->_dirty = true;
            $this->_dirty_props[$property] = true;
            return $this->setRaw($property, $value);
        }
       
        if(($method = $this->_mapPropertyToSetMethod($property)) && !in_array($property, array("relation", "get", "set")))
        {
            $this->_dirty = true;
            $this->_dirty_props[$property] = true;
            if (!is_string($method))
            {
                //var_dump(($method));
            }
            return $this->$method($property, $value);
        }

        if ($this->_relmapper->has($property))
        {
            
            return $this->_relmapper->set($property, $value);
        }

        if(!$this->isGuarded($property))
        {
            $this->setRaw($property, $value);
        }
    }

    function import($values)
    {
        if(!is_array($values))
        {
            return;
        }

        $this->_onBeforeImport();
        foreach($values as $property => $value)
        {
            if(!$this->isGuarded($property))
            {
                $this->set($property, $value);
            }
        }
        if (isset($values[$this->_config['pk']]))
        {
            $this->_id = $values[$this->_config['pk']];
        }
        $this->_onAfterImport();
    }

    function export()
    {
        $exported = array();
        foreach(get_object_vars($this) as $name => $var)
        {
            if(!$this->isGuarded($name))
            {
                $exported[$name] = $var;
            }
        }
        return $exported;
    }

    function getPropertiesNames()
    {
        return array_keys($this->export());
    }

    function remove($name)
    {
        if($this->hasRaw($name) && !$this->isGuarded($name))
        {
            unset($this->$name);
            $this->_dirty = true;
            $this->_dirty_props[$name] = true;
        }
    }




    //=================property checks
    //=======================
    //=======================
    function isPropertyDirty($property)
    {
        return array_key_exists($property, $this->_dirty_props);
    }

    protected function isGuarded($property)
    {
        return $property{0} == '_';
    }



    //===========================magic
    //=======================
    //=======================
    function __call($method, $args = array())
    {
        if($property = $this->_mapGetToProperty($method))
        {
            if($this->has($property))
            {
                return $this->get($property);
            }
            else
            {
                _D(debug_backtrace(), true);
                throw new pdException("No such method '$method' in " . get_class($this));
            }
        }
        elseif($property = $this->_mapSetToProperty($method))
        {
            if (!isset($args[0]))
            {
                mtoProfiler :: instance()->logError(_D(debug_backtrace(),1,1), "ao_EBANROT");
            }
            $this->set($property, $args[0]);
            return;
        }
        _D(debug_backtrace(), true);
        throw new pdException("No such method '$method' in " . get_class($this));
    }

    public function __set($property,$value)
    {
        if($this->hasRaw($property))
        {
            $this->setRaw($property, $value);
        }
        else
        {
            $this->set($property, $value);
        }
    }

    public function __get($property)
    {
        return $this->get($property);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }

    public function __unset($name)
    {
        return $this->remove($name);
    }

    protected function _mapGetToProperty($method)
    {
        if(substr($method, 0, 3) == 'get')
        {
            return mto_under_scores(substr($method, 3));
        }
    }

    protected function _mapSetToProperty($method)
    {
        if(substr($method, 0, 3) == 'set')
        {
            return mto_under_scores(substr($method, 3));
        }
    }

    protected function _mapPropertyToMethod($property)
    {
        static $map = array();
        if(isset($map[$property]))
        {
            return $map[$property];
        }

        $capsed = mto_camel_case($property);
        $method = 'get' . $capsed;
        if(method_exists($this, $method))
        {
            $map[$property] = $method;
            return $method;
        }
        if(strpos($property, 'is_') === 0 && method_exists($this, $capsed))
        {
            $map[$property] = $capsed;
            return $capsed;
        }
        $map[$property] = false;
    }

    protected function _mapPropertyToSetMethod($property)
    {
        if($this->isGuarded($property))
        {
            $property = substr($property, 1);
        }

        $method = 'set' . mto_camel_case($property);
        if(method_exists($this, $method))
        {
            return $method;
        }
    }





    //===================object handling
    //=======================
    //=======================
    protected function _getTableInfo()
    {
        $key = "db_".$this->_table_name;
        $tbl = $this->_cache->get($key);
        if (!$tbl)
        {
            $this->log("TBLF: " . $this->_table_name);
            $tbl = $this->_db->sql_tableinfo($this->_table_name);
            $this->_cache->set($key, $tbl, false, array(CACHE_TAG_DB));
        }
        else
        {
            $this->log("TBLC: " . $this->_table_name);
        }
        $this->_table_info = $tbl;
    }

    protected function _load($id = 0)
    {
        if ($id>0)
        {
            $this->_dirty = false;
            $this->_dirty_props = array();
            $properties = $this->_cache->get($this->getKey());            
            if (!$properties)
            {
                $this->log("LOADB: " . get_class($this) . "#" . $id);
                $properties = $this->_db->sql_getone("select * from {$this->_table_name} where {$this->_pm_key}=?", array($id));                
                if (!$properties)
                {
                    //_D(debug_backtrace(), true);
                    throw new mtoActiveObjectNotFoundException("Active Object: " . get_called_class()."#" . $id . " not found");
                }
                $this->_cache->set($this->getKey(), $properties, false, array(CACHE_TAG_MODEL, $this->_model_name));
                $this->log("PUSHC(load): " . get_class($this) . "#" . $id);
            }
            else
            {
                $this->log("LOADC: " . get_class($this) . "#" . $id);
            }

        }
        else
        {
            $this->log("FILL: " . get_class($this) . "#" . $id);
            $this->_dirty = true;
            $this->_dirty_props = array();            
            
            foreach ($this->_table_info as $field)
            {
                $properties[$field['name']] = '';
                $this->_dirty_props[$field['name']] = true;
            }
        }
        

        if($properties)
        {            
            $this->importRaw($properties, true);
        }
    }

    function cloneMe($clon=null,$m2m = true, $m2o = array())
    {
        $this->_onBeforeClone();
        if (!is_object($clon))
        {
            $class = get_class($this);
            $clon = new $class();
        }
        $data = $this->export();
        //var_dump($data);
        unset($data[$this->_pm_key]);
        $clon->import($data);
        foreach ($m2o as $key => $value)
        {
            $clon->set($key, $value);
        }
        $clon->_cloneFiles();
        $clon->save();
        $this->_relmapper->cloneAll($clon, $m2m);
        $clon->save();
        $this->_vomapper->cloneAll($clon);
        $clon->save();
        $this->_onAfterClone();
        return $clon;
    }

    function save()
    {
        if ($this->isNew())
        {
            $this->_is_beeing_creating = true;
        }
        else
        {
            $this->_is_beeing_saving = true;
        }
        $this->_onBeforeSave();
        $this->_relmapper->preSave();
        $this->_save();
        $this->_relmapper->postSave();
        $this->_cache->delete($this->getKey());
        $this->log("DELC: " . $this->getKey());

        $this->_onAfterSave();
        $this->_dirty = false;
        $this->_dirty_props = array();
        $this->_is_beeing_creating = false;
        $this->_is_beeing_saving = false;

        return $this->getId();
    }

    protected function _save()
    {
        $data = array();
        $id = $this->getId();
        foreach ($this->_table_info as $field)
        {
            if (array_key_exists($field['name'], $this->_dirty_props))
            {
                $data[$field['name']] = $this->get($field['name']);
            }
        }
        if (empty($data))
        {
            return $id;
        }
        if (!empty($id))
        {
            if (empty($data[$this->_pm_key]))
            {
                $data[$this->_pm_key] = $id;
            }
            $this->_db->sql_update($this->_table_name, $data, $this->_pm_key);
        }
        else
        {
            $sql = $this->_db->sql_getinsertsql($this->_table_name, $data);
            $this->_db->sql_query($sql);
            $this->_id = $this->_db->sql_nextid();
            $this->set($this->_config['pk'], $this->_id);
        }
        return $this->_id;
    }

    function destroy()
    {
        $this->_onBeforeDestroy();
        $this->_destroyFiles();
        $this->_relmapper->destroy();
        $this->_destroy();
        $this->_onAfterDestroy();
        $this->_cache->delete($this->getKey());
        $this->log("DEST: " . $this->getKey());
    }

    function _destroy()
    {
        $this->_db->sql_query('DELETE from '.$this->_table_name.' where '.$this->_pm_key.' = '.(int)$this->getId());
    }

    function reset()
    {
        foreach($this->getPropertiesNames() as $name)
        {
            unset($this->$name);
            $this->_dirty = true;
            $this->_dirty_props[$name] = true;
        }
    }

    function isNew()
    {
        return empty($this->_id);
    }

    function isDirty()
    {
        return (count($this->_dirty_props) > 0);
    }






    //================configuration
    //=======================
    //=======================
    function getColumnsConfig()
    {
        return $this->_config['columns'];
    }

    function getColumnConfig($name)
    {
        if (empty($name))
        {
            _D(debug_backtrace(), true);
            die("YA POMERLO");
        }
        if (!isset($this->_config['columns'][$name]))
        {
            throw new mtoActiveObjectCoreException("Unknown column " . $name . " for class " . get_class($this));
        }
        return $this->_config['columns'][$name];
    }

    function getConfig()
    {
        return $this->_config;
    }

    function getModelConfig($model)
    {
        $obj = mtoActiveObject :: create($model, 0);
        return $obj->getConfig();
    }

    function getParentModel($model)
    {
        foreach ($this->_config['columns'] as $index => $column)
        {
            if ($column['type'] == "m2o" && $column['rel_model'] == $model)
            {
                $column['index'] = $index;
                return $column;
            }
        }
        return null;
    }





    //===============array access
    //=======================
    //=======================
    function offsetExists($offset)
    {
        return $this->has($offset);
    }

    function offsetGet($offset)
    {
        return $this->get($offset);
    }

    function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    function offsetUnset($offset)
    {
        $this->remove($offset);
    }



    //========================fetching
    //=======================
    //=======================
    function fetch($sql, $binding = array())
    {
        return $this->_db->sql_getall($sql, $binding);
    }

    function fetchRow($sql)
    {
        return $this->_db->sql_getone($sql);
    }

    function fetchValue($sql)
    {
        $row = $this->_db->sql_getone($sql);
        if (is_array($row))
        {
            return array_pop($row);
        }
        else
        {
            return null;
        }
    }

    function getNextPk()
    {
        return $this->fetchValue("select max(".$this->_config['pk'].")+1 from ".$this->_config['table_name']);
    }

    function getLastPk()
    {
        return $this->fetchValue("select max(".$this->_config['pk'].") from ".$this->_config['table_name']);
    }

    /**
     *
     * TODO: add caching to find methods
     */
    static function find($params)
    {
        $config = self :: create(get_called_class())->getConfig();
        $sql = "select " . (isset($params['offset']) && isset($params['limit']) ? "sql_calc_found_rows " : "") . $config['pk'] . " from " . $config['table_name'];
        if (isset($params['condition']) && !empty($params['condition']))
        {
            if (!is_array($params['condition']))
            {
                throw new mtoActiveObjectCoreException("Invalid condition for model: " . get_called_class());
            }
            $sql .= " where " . implode(" AND ", $params['condition']);
        }
        if (isset($params['order_by']))
        {
            $sql .= " order by " . $params['order_by'];
            if (isset($params['order_dir']))
            {
                $sql .= " " . $params['order_dir'];
            }
        }
        if (isset($params['offset']) && isset($params['limit']) && $params['limit']>0)
        {
            $sql .= " limit " . intval($params['offset']) . ", " . intval($params['limit']);
        }
        
        
        $binding = isset($params['binding']) ? $params['binding'] : array();

        if (isset($params['full']))
        {
            $data = self :: findByFullSql($sql, $binding);
        }
        else
        {
            $data = self :: findByIdSql($sql, $binding);

        }
        if (isset($params['offset']) && isset($params['limit']))
        {
            $real_count = mtoToolkit::instance()->getDbConnection()->fetchOneValue("select found_rows() as total_rows");
        }

        $dataset = array();
        $class = get_called_class();
        foreach ($data as $row)
        {
            if ($row instanceof mtoActiveObject)
            {
                $dataset[] = $row;
            }
            else
            {
                $dataset[] = new $class(intval($row[$config['pk']]));
            }
        }

        $collection = new mtoActiveObjectCollection($dataset);
        if (isset($params['offset']) && isset($params['limit']))
        {
            $collection->setCount($real_count);
        }
        return $collection;
    }

    static function findFirst($params)
    {
        $params['offset'] = 0;
        $params['limit'] = 1;
        $collection = self::find($params);
        if ($collection->count())
        {
            return $collection->at(0);
        }

    }

    static function findOne($params)
    {
        return self::findFirst($params);
    }

    static function findByIdSql($sql, $binding = array())
    {
        $class = get_called_class();
        $object = new $class();
        return $object->fetch($sql, $binding);
    }

    static function findByFullSql($sql, $binding = array())
    {
        $class = get_called_class();
        $object = new $class();
        $data = array();
        $collection = $object->fetch($sql, $binding);
        

        foreach ($collection as $item)
        {
            
            if (!empty($item[$object->_pm_key]))
            {
                $o = new $class($item[$object->_pm_key]);
            } else
            {
                $o = new $class();
                $o->import($item);
            }

            $data[] = $o;
        }
        return $data;
    }

    static function findByIds($ids = array())
    {
        if (!is_array($ids))
        {
            $ids = array($ids);
        }
        $class = get_called_class();
        $dataset = array();
        foreach ($ids as $id)
        {
            $dataset[] = new $class($id);
        }
        $collection = new mtoActiveObjectCollection($dataset);
        return $collection;
    }




    //=====================relations
    //=======================
    //=======================
    function addToRelation($property, $value)
    {
        return $this->_relmapper->add($property, $value);
    }

    function cleanRelation($property)
    {
        return $this->_relmapper->clean($property);
    }

    function removeFromRelationByField($property, $field, $value)
    {
        return $this->_relmapper->removeByField($property, $field, $value);
    }

    function removeFromRelationById($property, $id)
    {
        return $this->_relmapper->removeById($property, $id);
    }

    function getRelationIds($property)
    {
        return $this->_relmapper->getIds($property);
    }

    function getRelValue($property)
    {
        return $this->_relmapper->getIds($property);
    }

    function getRelList($property, $order_by='', $order='')
    {
        return $this->_relmapper->get($property);
    }

    function getRelationConfig($property)
    {
        return $this->getColumnConfig($property);
    }


    //-----------------------------------------------------
    //=======================
    //=======================





















    function getKey()
    {
        return "model_".get_class($this)."_".$this->_id;
    }

    function getRelKey($property)
    {
        return $this->_relmapper->getKey($property);
    }





    function __toString()
    {
        return "Instance of " . get_called_class() . ", ID: " . $this->getId();
    }

    function dump()
    {
        $record = $this->export();
        foreach ($record as $k => $v)
        {
            var_dump($k);
            if ($v instanceof mtoActiveObject)
            {
                $record[$k] = $v->__toString();
            }
            else
            {
                $record[$k] = $v;
            }
        }
        var_dump($record);
    }

    function dumpMany()
    {
        $this->_relmapper->dumpMany();
    }

    function getCaptionFieldValue()
    {
        if (isset($this->_config['name_field']))
        {
            return $this->get($this->_config['name_field']);
        }
    }


    function getGuarded($prop)
    {
        $prop = "_".$prop;
        return property_exists($this, $prop) ? $this->$prop : null;
    }


    protected function _destroyFiles()
    {
        foreach($this->_config['columns'] as $index => $col)
        {
            switch($col['type'])
            {
                case 'image':
                case 'path':
                    $path = $this->_toolkit->generateFilename($col['path_type'], $this->get($index));
                    if (file_exists($path))
                    {
                        @unlink($path);
                    }
                break;
            }
        }

    }

    protected function _cloneFiles()
    {
        foreach ($this->_config['columns'] as $index => $col)
        {
            if (!empty($col['value_object']))
            {
                continue;
            }
            switch ($col['type'])
            {
                case "image":
                case "path":
                    $path = $this->_toolkit->generateFilename($col['path_type'], $this->get($index));
                    if (file_exists($path) && is_file($path))
                    {
                        $target = uniqid("clone").".jpg";
                        $this->set($index, $target);
                        $file_data = file_get_contents($path);
                        mtoFs :: safeWrite($this->_toolkit->generateFilename($col['path_type'], $target), $file_data);
                    }
                break;
            }
        }
    }




    //next methods can be overrided
    protected function _onBeforeSave() {}

    protected function _onAfterSave() {}

    protected function _onBeforeDestroy() {}

    protected function _onAfterDestroy() {}

    protected function _onBeforeImport() {}

    protected function _onAfterImport() {}

    protected function _onBeforeClone() {}

    protected function _onAfterClone() {}

    




    function log($msg)
    {
        if (mtoConf :: instance()->get("ao", "logging"))
        {
            $msg .= "\t" . get_class($this) . "#" . $this->_id;
            mtoProfiler :: instance()->logDebug($msg, "ao");
        }
    }




}

