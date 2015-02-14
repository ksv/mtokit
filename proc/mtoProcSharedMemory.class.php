<?php

class mtoIpcSharedMemory
{
    const MEMORY_SIZE = 524288;
    const PERMISSIONS = 0777;

    protected $key;
    protected $memorySize;
    protected $permissions;
    protected $shmId;
    protected static $namesCache = array();

    public static function instance($file, $proj = 'm', $memorySize = self::MEMORY_SIZE, $permissions = self::PERMISSIONS)
    {
        $key = ftok($file, $proj);
        if ($key === -1)
        {
            throw new mtoException("Can't initialize System V shared memory with file [$file] and project [$proj]", 1);
        }
        return new self($key, $memorySize, $permissions);
    }

    public function __construct($key, $memorySize = self::MEMORY_SIZE, $permissions = self::PERMISSIONS)
    {
        if (!function_exists('shm_attach'))
        {
            throw new mtoException('You need php compiled with --enable-sysvshm option to work with System V shared memory', 1);
        }
        if (!is_numeric($key))
        {
            $key = sprintf('%u', crc32($key));
        }
        $this->key = (int) $key;
        $this->memorySize = $memorySize;
        $this->permissions = $permissions;
    }

    public function __destruct()
    {
        $this->detach();
    }

    protected function init()
    {
        if (null === $this->shmId)
        {
            $this->shmId = shm_attach($this->key, $this->memorySize, $this->permissions);
        }
    }

    protected function name($name)
    {
        if (!isset(self::$namesCache[$name]))
        {
            self::$namesCache[$name] = sprintf('%u', crc32($name));
        }
        return self::$namesCache[$name];
    }

    public function detach()
    {
        if (null !== $this->shmId)
        {
            shm_detach($this->shmId);
            $this->shmId = null;
        }
    }

    public function destroy()
    {
        $this->init();
        if (!shm_remove($this->shmId))
        {
            throw new mtoException("Can't destroy shared memory segment", 1);
        }
        $this->detach();
    }

    public function get($name)
    {
        $this->init();
        return $this->_get($this->name($name));
    }

    public function getOnce($name)
    {
        $this->init();
        $key = $this->name($name);
        $value = $this->_get($key);
        $this->_delete($key);
        return $value;
    }

    public function containsKey($name)
    {
        $this->init();
        return false !== $this->_get($this->name($name), false);
    }

    public function set($name, $value)
    {
        // shm_get_var return false in case of non-existed key.
        // We need a wrapper to store FALSE values
        if (false === $value)
        {
            $value = (object) array('__shmFalseWrapper' => true);
        }

        $this->init();
        if (!shm_put_var($this->shmId, $key = $this->name($name), $value))
        {
            throw new mtoException("Can't set var '$key' ($name) into shared memory", 1);
        }
    }

    public function delete($name)
    {
        $this->init();
        $this->_delete($this->name($name));
    }

    protected function _get($key, $process = true)
    {
        $value = @shm_get_var($this->shmId, $key);
        if ($process)
        {
            return $value === false ? null : (isset($value->__shmFalseWrapper) ? false : $value);
        }
        return $value;
    }

    protected function _delete($key)
    {
        if (!shm_remove_var($this->shmId, $key))
        {
            throw new mtoException("Can't delete var '$key' from shared memory", 2);
        }
    }

}
