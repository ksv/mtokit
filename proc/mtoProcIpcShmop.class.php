<?php

class mtoIpcShmop
{
    const MODE_READ = 'a';
    const MODE_CREATE_READ_WRITE = 'c';
    const MODE_READ_WRITE = 'w';
    const MODE_CREATE_READ_WRITE_EXCL = 'n';
    const MEMORY_SIZE = 524288;
    const PERMISSIONS = 0777;
    protected $key;
    protected $mode;
    protected $memorySize;
    protected $permissions;
    protected $shmId;

    public static function instance($file, $proj = 'm', $mode = self::MODE_CREATE_READ_WRITE, $memorySize = self::MEMORY_SIZE, $permissions = self::PERMISSIONS)
    {
        $key = ftok($file, $proj);
        if ($key === -1)
        {
            throw new mtoException("Can't initialize shared memory with file [$file] and project [$proj]", 1);
        }
        return new self($key, $mode, $memorySize, $permissions);
    }

    public static function useIgbinary()
    {
        return function_exists('igbinary_serialize');
    }

    public function __construct($key, $mode = self::MODE_CREATE_READ_WRITE, $memorySize = self::MEMORY_SIZE, $permissions = self::PERMISSIONS)
    {
        if (!function_exists('shmop_open'))
        {
            throw new mtoException('You need php compiled with --enable-shmop option to work with shared memory', 1);
        }
        if (!is_numeric($key))
        {
            $key = sprintf('%u', crc32($key));
        }
        $this->key = (int) $key;
        $this->mode = $mode;
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
            if (!$this->shmId = shmop_open($this->key, $this->mode, $this->permissions, $this->memorySize))
            {
                $msg = sprintf(
                                "Can't open shared memory segment (key: %d, mode: %s, permissions: 0%o, size: %d)",
                                $this->key, $this->mode, $this->permissions, $this->memorySize
                );
                throw new mtoException($msg, 1);
            }
        }
    }

    public function detach()
    {
        if (null !== $this->shmId)
        {
            shmop_close($this->shmId);
            $this->shmId = null;
        }
    }

    public function destroy()
    {
        $this->init();
        if (!shmop_delete($this->shmId))
        {
            $msg = sprintf("Can't destroy shared memory segment (shmId: 0x%s)", $this->shmId);
            throw new mtoException($msg, 1);
        }
        $this->detach();
    }

    public function size()
    {
        $this->init();
        return shmop_size($this->shmId);
    }

    public function read($offset = 0, $length = 0)
    {
        $this->init();
        $res = shmop_read($this->shmId, $offset, $length);
        if (false === $res)
        {
            $msg = sprintf(
                            "Can't read data from shared memory (shmId: 0x%s, offset: %d, length: %d)",
                            $this->shmId, $offset, $length
            );
            throw new mtoException($msg, 1);
        }
        // Check for nulls
        if ("\0" === $res[strlen($res) - 1])
        {
            $res = rtrim($res, "\0");
        }
        if ("\0" === $res[0])
        {
            $res = ltrim($res, "\0");
        }
        // Serialized data
        if ("\2" === $res[0] && "\3" === $res[strlen($res) - 1])
        {
            $res = substr($res, 1, -1);
            $res = self::useIgbinary() ? igbinary_unserialize($res) : unserialize($res);
        }
        return $res;
    }

    public function write($data, $offset = 0)
    {
        $this->init();
        if (!is_scalar($data))
        {
            $data = self::useIgbinary() ? igbinary_serialize($data) : serialize($data);
            $data = "\2{$data}\3";
        }
        if (!$res = shmop_write($this->shmId, $data, $offset))
        {
            $msg = sprintf(
                            "Can't write data to shared memory (shmId: 0x%s, offset: %d, data length: %d)",
                            $this->shmId, $offset, strlen($data)
            );
            throw new mtoException($msg, 1);
        }
        return $res;
    }

}

