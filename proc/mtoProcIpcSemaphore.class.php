<?php

class mtoIpcSemaphore
{
    const PERMISSIONS = 0777;
    protected $key;
    protected $maxAcquire;
    protected $permissions;
    protected $autoRelease;
    protected $semId;

    public static function instance($file, $proj = 's', $max_acquire = 1, $permissions = self::PERMISSIONS, $auto_release = 1)
    {
        $key = ftok($file, $proj);
        if ($key === -1)
        {
            throw new mtoException("Can't initialize semaphore with file [$file] and project [$proj]", 1);
        }
        return new self($key, $max_acquire, $permissions, $auto_release);
    }

    public function __construct($key, $max_acquire = 1, $permissions = self::PERMISSIONS, $auto_release = 1)
    {
        if (!function_exists('sem_get'))
        {
            throw new mtoException('You need php compiled with --enable-sysvsem option to work with semaphore', 1);
        }
        if (!is_numeric($key))
        {
            $key = sprintf('%u', crc32($key));
        }
        $this->key = (int) $key;
        $this->permissions = $permissions;
        $this->maxAcquire = $max_acquire;
        $this->autoRelease = $auto_release;
    }

    protected function init()
    {
        if (null === $this->semId)
        {
            if (!$this->semId = sem_get($this->key, $this->maxAcquire, $this->permissions, $this->autoRelease))
            {
                throw new mtoException("Can't create semaphore [$this->key, $this->maxAcquire, $this->permissions, $this->autoRelease]", 1);
            }
        }
    }

    public function remove()
    {
        $this->init();
        @sem_remove($this->semId);
        $this->semId = null;
    }

    public function acquire()
    {
        $this->init();
        return sem_acquire($this->semId);
    }

    public function release()
    {
        $this->init();
        return sem_release($this->semId);
    }

}
